<?php

namespace Tests\Feature;

use App\Jobs\MoveToDropboxJob;
use App\Models\R2PendingUpload;
use App\Models\Task;
use App\Models\Team;
use App\Services\DropboxService;
use App\Services\PhotoDelivery;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhotoDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Http::preventStrayRequests();
        Storage::fake('r2');
        $team = Team::create(['name' => 'Photo test team', 'password' => 'test']);
        $address = DB::table('addresses')->insertGetId(['street' => 'Test', 'number' => '1', 'zipcode' => '1000', 'city' => 'Test']);
        $this->task = Task::create(['team_id' => $team->id, 'address_id' => $address, 'time' => now(), 'status' => 'open']);
        $this->actingAs($team);
        $dropbox = \Mockery::mock(DropboxService::class);
        $dropbox->shouldReceive('getFluviusNamespaceId')->andReturn('namespace-2');
        $dropbox->shouldReceive('getAccessToken')->andReturn('test-token');
        $dropbox->shouldReceive('renewAccessToken')->andReturnNull();
        $this->app->instance(DropboxService::class, $dropbox);
    }

    private function receive(?string $id = null, string $content = 'photo contents', string $folder = 'Webapp uploads/Test, 1')
    {
        return $this->postJson('/r2/upload?sw_bypass=true', [
            'unique_id' => $id ?? (string) Str::uuid(), 'task_id' => $this->task->id,
            'namespace_id' => 'namespace-2', 'adres_path' => $folder,
            'file' => UploadedFile::fake()->createWithContent('same-name.jpg', $content),
        ]);
    }

    private function successDropbox(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['content.dropboxapi.com/*' => function ($request) {
            $arg = json_decode($request->header('Dropbox-API-Arg')[0], true);

            return Http::response(['id' => 'id:photo', 'size' => strlen($request->body()),
                'content_hash' => $arg['content_hash'], 'path_display' => $arg['path']], 200);
        }]);
    }

    private function runJob(R2PendingUpload $row): void
    {
        (new MoveToDropboxJob([$row->r2_path], $row->adres_path, $row->namespace_id, $row->task_id))->handle();
    }

    public function test_successful_handoff_and_delivery_retain_receipt_and_cleanup_only_after_confirmation(): void
    {
        $this->receive()->assertOk()->assertJson(['persisted' => true]);
        $row = R2PendingUpload::firstOrFail();
        Storage::disk('r2')->assertExists($row->r2_path);
        $this->assertNotNull($row->confirmed_at);
        Bus::assertDispatched(MoveToDropboxJob::class);
        $this->successDropbox();
        $this->runJob($row);
        $row->refresh();
        $this->assertNotNull($row->completed_at);
        $this->assertNotNull($row->cleaned_at);
        Storage::disk('r2')->assertMissing($row->r2_path);
        $this->assertStringContainsString('%2C', $this->task->fresh()->photo);
        $this->assertCount(1, explode(',', $this->task->fresh()->photo));
    }

    public function test_lost_browser_response_and_duplicate_request_reuse_the_same_receipt_even_after_cleanup(): void
    {
        $id = (string) Str::uuid();
        $this->receive($id)->assertOk();
        $this->receive($id)->assertOk();
        $this->assertDatabaseCount('r2_pending_uploads', 1);
        $this->successDropbox();
        $this->runJob(R2PendingUpload::first());
        $this->receive($id)->assertOk()->assertJson(['persisted' => true]);
        $this->assertDatabaseCount('r2_pending_uploads', 1);
        $this->assertCount(0, Storage::disk('r2')->allFiles());
        Http::assertSentCount(1);
    }

    public function test_same_name_different_ids_keep_both_photos_and_content_reuse_is_rejected(): void
    {
        $id = (string) Str::uuid();
        $this->receive($id, 'first')->assertOk();
        $this->receive(null, 'second')->assertOk();
        $this->receive($id, 'wrong replacement')->assertStatus(409);
        $this->assertDatabaseCount('r2_pending_uploads', 2);
        $this->assertCount(2, Storage::disk('r2')->allFiles());
    }

    public function test_registration_cannot_acknowledge_missing_r2_object(): void
    {
        $this->postJson('/r2/register-upload', ['task_id' => $this->task->id,
            'namespace_id' => 'namespace-2', 'adres_path' => 'Test', 'r2_path' => 'missing.jpg'])->assertStatus(409);
        $this->assertDatabaseCount('r2_pending_uploads', 0);
    }

    public function test_task_completion_waits_for_all_receipts_and_replay_cannot_advance_status_twice(): void
    {
        $ids = [(string) Str::uuid(), (string) Str::uuid()];
        $finish = ['damage' => 'none', 'request_id' => (string) Str::uuid(), 'upload_ids' => $ids];
        $this->receive($ids[0])->assertOk();
        $this->postJson('/tasks/'.$this->task->id.'/finish', $finish)->assertStatus(409);
        $this->assertSame('open', $this->task->fresh()->status);
        $this->receive($ids[1])->assertOk();
        $this->postJson('/tasks/'.$this->task->id.'/finish', $finish)->assertOk()->assertJson(['status' => 'in behandeling']);
        $this->postJson('/tasks/'.$this->task->id.'/finish', $finish)->assertOk()->assertJson(['status' => 'in behandeling']);
        $this->assertSame('in behandeling', $this->task->fresh()->status);
        $this->assertDatabaseCount('photo_finish_requests', 1);
        $this->assertNull(R2PendingUpload::first()->completed_at); // Does not wait for Dropbox.
    }

    public function test_dropbox_failure_retains_r2_and_receipt_then_retry_succeeds(): void
    {
        $this->receive()->assertOk();
        $row = R2PendingUpload::first();
        Http::fake(['*' => Http::response([], 503)]);
        try {
            $this->runJob($row);
            $this->fail('Failure must escape the job.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('503', $e->getMessage());
        }
        Storage::disk('r2')->assertExists($row->r2_path);
        $this->assertSame('failed', $row->fresh()->status);
        $this->successDropbox();
        $this->runJob($row);
        $this->assertNotNull($row->fresh()->completed_at);
    }

    public function test_429_honors_retry_after_and_retains_source(): void
    {
        $this->receive()->assertOk();
        $row = R2PendingUpload::first();
        Http::fake(['*' => Http::response([], 429, ['Retry-After' => '120'])]);
        $job = new MoveToDropboxJob([$row->r2_path], $row->adres_path, $row->namespace_id, $row->task_id);
        $queued = \Mockery::mock(Job::class);
        $queued->shouldReceive('release')->once()->with(120);
        $job->setJob($queued);
        $job->handle();
        Storage::disk('r2')->assertExists($row->r2_path);
        $this->assertNull($row->fresh()->completed_at);
    }

    public function test_401_refreshes_token_and_rewinds_the_entire_photo(): void
    {
        $this->receive()->assertOk();
        $calls = 0;
        Http::fake(['*' => function ($request) use (&$calls) {
            $calls++;
            if ($calls === 1) {
                return Http::response([], 401);
            }
            $arg = json_decode($request->header('Dropbox-API-Arg')[0], true);
            $this->assertSame('photo contents', $request->body());

            return Http::response(['id' => 'id:photo', 'size' => strlen($request->body()),
                'content_hash' => $arg['content_hash'], 'path_display' => $arg['path']]);
        }]);
        $this->runJob(R2PendingUpload::first());
        $this->assertSame(2, $calls);
    }

    public function test_duplicate_job_does_not_upload_or_append_twice(): void
    {
        $this->receive()->assertOk();
        $this->successDropbox();
        $row = R2PendingUpload::first();
        $this->runJob($row);
        $this->runJob($row);
        Http::assertSentCount(1);
        $this->assertCount(1, explode(',', $this->task->fresh()->photo));
    }

    public function test_cleanup_failure_can_retry_without_uploading_again(): void
    {
        $this->receive()->assertOk();
        $this->successDropbox();
        $row = R2PendingUpload::first();
        $delivery = \Mockery::mock(PhotoDelivery::class)->makePartial();
        $delivery->shouldReceive('cleanup')->once()->andThrow(new \RuntimeException('Simulated cleanup failure'));
        $this->app->instance(PhotoDelivery::class, $delivery);
        try {
            $this->runJob($row);
            $this->fail('Cleanup must be retryable.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated cleanup failure', $e->getMessage());
        }
        $this->assertNotNull($row->fresh()->completed_at);
        Storage::disk('r2')->assertExists($row->r2_path);
        $this->app->instance(PhotoDelivery::class, new PhotoDelivery);
        $this->runJob($row);
        Http::assertSentCount(1);
        Storage::disk('r2')->assertMissing($row->r2_path);
    }

    public function test_invalid_dropbox_success_keeps_the_only_source(): void
    {
        $this->receive()->assertOk();
        $row = R2PendingUpload::first();
        Http::fake(['*' => Http::response(['id' => 'id:wrong', 'size' => 1, 'content_hash' => 'bad'], 200)]);
        try {
            $this->runJob($row);
            $this->fail('Must reject false success.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('acknowledgement', $e->getMessage());
        }
        Storage::disk('r2')->assertExists($row->r2_path);
        $this->assertNull($row->fresh()->completed_at);
    }

    public function test_stale_processing_and_missing_sources_remain_recoverable(): void
    {
        $this->receive()->assertOk();
        $row = R2PendingUpload::first();
        $row->forceFill(['status' => 'processing', 'updated_at' => now()->subHour()])->save();
        Storage::disk('r2')->delete($row->r2_path); // Simulated external loss.
        Bus::fake();
        $this->artisan('r2:retry-all')->assertSuccessful();
        Bus::assertDispatched(MoveToDropboxJob::class);
        $this->assertDatabaseHas('r2_pending_uploads', ['id' => $row->id]);
    }

    public function test_object_lock_prevents_overlapping_worker_and_ingress(): void
    {
        $id = (string) Str::uuid();
        $this->receive($id)->assertOk();
        $row = R2PendingUpload::first();
        $lock = Cache::lock(PhotoDelivery::lockKey($row->r2_path), 600);
        $this->assertTrue($lock->get());
        try {
            $this->receive($id)->assertStatus(409);
        } finally {
            $lock->release();
        }
        Storage::disk('r2')->assertExists($row->r2_path);
    }

    public function test_deleted_task_does_not_delete_recovery_metadata_or_stop_delivery(): void
    {
        $this->receive()->assertOk();
        $row = R2PendingUpload::first();
        $this->task->delete();
        $this->assertDatabaseHas('r2_pending_uploads', ['id' => $row->id]);
        $this->successDropbox();
        $this->runJob($row);
        $this->assertNotNull($row->fresh()->completed_at);
    }

    public function test_thirty_photos_with_one_failure_preserve_all_receipts_and_other_references(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->receive(null, 'photo '.$i)->assertOk();
        }
        $this->assertDatabaseCount('r2_pending_uploads', 30);
        $rows = R2PendingUpload::all();
        Http::fake(['*' => Http::response([], 503)]);
        try {
            $this->runJob($rows[0]);
        } catch (\RuntimeException) {
        }
        $this->successDropbox();
        foreach ($rows->skip(1) as $row) {
            $this->runJob($row);
        }
        $this->assertSame(29, R2PendingUpload::whereNotNull('completed_at')->count());
        $this->assertCount(29, explode(',', $this->task->fresh()->photo));
        Storage::disk('r2')->assertExists($rows[0]->r2_path);
    }
    public function test_r2_failure_keeps_receiving_record_and_never_acknowledges(): void
    {
        $disk = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('putFileAs')->andReturn(false);
        Storage::shouldReceive('disk')->with('r2')->andReturn($disk);
        $this->receive()->assertStatus(500);
        $row = R2PendingUpload::firstOrFail();
        $this->assertNull($row->confirmed_at);
        $this->assertSame('receiving', $row->status);
        Bus::assertNotDispatched(MoveToDropboxJob::class);
    }

    public function test_queue_dispatch_failure_still_has_a_durable_outbox_receipt(): void
    {
        Bus::shouldReceive('dispatch')->andThrow(new \RuntimeException('Queue unavailable'));
        $this->receive()->assertOk()->assertJson(['persisted' => true]);
        $row = R2PendingUpload::firstOrFail();
        $this->assertNotNull($row->confirmed_at);
        Storage::disk('r2')->assertExists($row->r2_path);
    }

    public function test_size_equal_but_corrupt_existing_object_is_not_acknowledged(): void
    {
        $id = (string) Str::uuid();
        $this->receive($id, 'first')->assertOk();
        $row = R2PendingUpload::first();
        Storage::disk('r2')->put($row->r2_path, 'wrong');
        $this->receive($id, 'first')->assertStatus(500);
        $this->assertSame('wrong', Storage::disk('r2')->get($row->r2_path));
        $this->assertNull($row->fresh()->completed_at);
    }

    public function test_crash_after_r2_put_before_confirmation_is_recovered_by_worker(): void
    {
        $this->receive()->assertOk();
        $row = R2PendingUpload::first();
        $row->update(['status' => 'receiving', 'confirmed_at' => null]);
        $this->successDropbox();
        $this->runJob($row);
        $this->assertNotNull($row->fresh()->completed_at);
    }

    public function test_unconfirmed_object_cannot_be_cleaned(): void
    {
        $this->receive()->assertOk();
        $row = R2PendingUpload::first();
        try {
            (new PhotoDelivery)->cleanup($row);
            $this->fail('Cleanup must refuse unconfirmed Dropbox delivery.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Refusing cleanup', $e->getMessage());
        }
        Storage::disk('r2')->assertExists($row->r2_path);
    }

    public function test_perceel_one_namespace_and_special_characters_use_confirmed_path(): void
    {
        $this->receive(null, 'photo', 'PERCEEL 1/Webapp uploads/École, 50%')->assertOk();
        $row = R2PendingUpload::first();
        $row->update(['namespace_id' => 'namespace-1']);
        $this->successDropbox();
        $this->runJob($row);
        $this->assertStringStartsWith('/Webapp uploads/', $row->fresh()->target_dropbox_path);
        $this->assertStringStartsWith('/PERCEEL 1/Webapp uploads/', $this->task->fresh()->photo);
        Http::assertSent(function ($request) {
            $root = json_decode($request->header('Dropbox-API-Path-Root')[0], true);
            return $root['namespace_id'] === 'namespace-1';
        });
    }

    public function test_truncated_second_r2_read_cannot_be_committed_to_dropbox(): void
    {
        $source = fopen('php://temp', 'w+');
        fwrite($source, 'partial'); rewind($source);
        try {
            app(\App\Services\DropboxPhotoUploader::class)->upload(app(DropboxService::class),
                'namespace-2', '/photo.jpg', $source, hash('sha256', 'complete photo'), 14);
            $this->fail('Partial stream must never reach Dropbox.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('incomplete', $e->getMessage());
            Http::assertNothingSent();
        } finally { fclose($source); }
    }

    public function test_dashboard_renders_shared_queue_and_recovery_controls(): void
    {
        $this->withoutVite();
        $this->get('/dashboard/user')->assertOk()
            ->assertSee('/js/photo-queue.js?v=8', false)
            ->assertSee('photo-finish:', false);
    }

}
