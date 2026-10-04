<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class DropboxPhotoUploader
{
    // Copy to a seekable temporary stream, calculate Dropbox's block hash, and
    // validate the entire response. The durable R2 source is retained throughout.
    public function upload(DropboxService $dropbox, string $namespace, string $path, $source, ?string $expectedSha256 = null, ?int $expectedSize = null): array
    {
        $stream = tmpfile();
        if (! $stream || stream_copy_to_stream($source, $stream) === false) {
            throw new RuntimeException('Cannot prepare R2 stream for Dropbox.');
        }
        try {
            rewind($stream);
            $blocks = '';
            $sha256 = hash_init('sha256');
            while (! feof($stream)) {
                $block = fread($stream, 4194304);
                if ($block === false) {
                    throw new RuntimeException('Cannot read photo stream.');
                }
                if ($block !== '') {
                    $blocks .= hash('sha256', $block, true);
                    hash_update($sha256, $block);
                }
            }
            $hash = hash('sha256', $blocks);
            $size = fstat($stream)['size'];
            $actualSha = hash_final($sha256);
            if (($expectedSize !== null && $expectedSize !== $size) ||
                ($expectedSha256 !== null && !hash_equals($expectedSha256, $actualSha))) {
                throw new RuntimeException('R2 stream was incomplete or changed; source retained.');
            }
            for ($attempt = 0; $attempt < 2; $attempt++) {
                rewind($stream);
                $response = Http::withToken($dropbox->getAccessToken())->connectTimeout(10)->timeout(90)
                    ->withHeaders([
                        'Content-Type' => 'application/octet-stream',
                        'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'),
                        'Dropbox-API-Path-Root' => json_encode(['.tag' => 'namespace_id', 'namespace_id' => $namespace]),
                        'Dropbox-API-Arg' => json_encode(['path' => $path, 'mode' => 'overwrite',
                            'autorename' => false, 'mute' => false, 'content_hash' => $hash]),
                    ])->send('POST', 'https://content.dropboxapi.com/2/files/upload', ['body' => $stream]);
                if ($response->status() === 401 && $attempt === 0) {
                    $dropbox->renewAccessToken();

                    continue;
                }
                if ($response->status() === 429) {
                    throw new DropboxPhotoRetry(max(1, (int) $response->header('Retry-After')));
                }
                if ($response->status() !== 200) {
                    throw new RuntimeException('Dropbox upload HTTP '.$response->status());
                }
                $metadata = $response->json();
                if (! is_array($metadata) || empty($metadata['id']) ||
                    ($metadata['size'] ?? -1) !== $size || ($metadata['content_hash'] ?? '') !== $hash ||
                    mb_strtolower($metadata['path_display'] ?? '') !== mb_strtolower($path)) {
                    throw new RuntimeException('Dropbox acknowledgement does not match the complete photo.');
                }

                return $metadata;
            }
            throw new RuntimeException('Dropbox authorization failed.');
        } finally {
            fclose($stream);
        }
    }
}
