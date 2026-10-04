<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('r2_pending_uploads', function (Blueprint $table) {
            // Preserve recovery metadata even when the task/team is deleted.
            $table->dropForeign(['task_id']);
            $table->uuid('upload_id')->nullable()->unique();
            $table->string('content_sha256', 64)->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cleaned_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
        });
        Schema::create('photo_finish_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('task_id');
            $table->json('response');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_finish_requests');
        Schema::table('r2_pending_uploads', function (Blueprint $table) {
            $table->dropUnique(['upload_id']);
            $table->dropColumn(['upload_id', 'content_sha256', 'byte_size', 'confirmed_at', 'completed_at', 'cleaned_at', 'attempts']);
        });
        // Do not restore cascading deletion or delete orphaned recovery records.
    }
};
