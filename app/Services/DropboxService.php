<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DropboxService
{
    protected $accessToken;

    public function __construct()
    {
        $this->accessToken = $this->refreshAccessToken();
    }

    private function refreshAccessToken()
    {
        $response = Http::asForm()->withBasicAuth(
            config('services.dropbox.app_key'),
            config('services.dropbox.app_secret')
        )->post('https://api.dropboxapi.com/oauth2/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => config('services.dropbox.refresh_token'),
        ]);

        if ($response->failed()) {
            throw new \Exception("Dropbox token refresh failed: " . $response->body());
        }

        return $response->json()['access_token'];
    }

    /**
     * Haal alle namespaces (team folders, shared folders, etc.)
     */
    public function listNamespaces()
    {
        $response = Http::withToken($this->accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('https://api.dropboxapi.com/2/team/namespaces/list', (object)[]);

        if ($response->failed()) {
            throw new \Exception("Dropbox namespaces ophalen mislukt: " . $response->body());
        }

        return $response->json()['namespaces'] ?? [];
    }

    /**
     * Geef de namespace_id van "Fluvius Aansluitingen"
     */
    public function getFluviusNamespaceId()
    {
        $namespaces = $this->listNamespaces();

        if (empty($namespaces)) {
            throw new \Exception("Geen namespaces gevonden! Response leeg.");
        }

        $fluvius = collect($namespaces)
            ->first(fn($ns) => stripos($ns['name'], 'fluvius aansluitingen') !== false);

        if (!$fluvius) {
            throw new \Exception("Fluvius Aansluitingen namespace niet gevonden. Beschikbare: " . json_encode($namespaces));
        }

        return $fluvius['namespace_id'];
    }

    /**
     * Eerste batch van submappen/bestanden binnen een namespace
     */
    public function listFoldersInNamespace($namespaceId, $path = "")
    {
        $headers = [
            'Dropbox-API-Path-Root' => json_encode([
                '.tag'         => 'namespace_id',
                'namespace_id' => $namespaceId
            ]),
            'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'),
        ];

        $response = Http::withToken($this->accessToken)
            ->withHeaders($headers)
            ->post('https://api.dropboxapi.com/2/files/list_folder', [
                'path'      => $path,
                'recursive' => false,
                'limit'     => 250,
            ]);

        if ($response->failed()) {
            throw new \Exception("Dropbox list_folder failed: " . $response->body());
        }

        $result = $response->json();

        return [
            'entries'  => $result['entries'] ?? [],
            'cursor'   => $result['cursor'] ?? null,
            'has_more' => $result['has_more'] ?? false,
        ];
    }

    /**
     * Vervolgbatch ophalen met cursor
     */
    public function listFoldersContinue($namespaceId, $cursor)
    {
        $headers = [
            'Dropbox-API-Path-Root' => json_encode([
                '.tag'         => 'namespace_id',
                'namespace_id' => $namespaceId
            ]),
            'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'),
        ];

        $response = Http::withToken($this->accessToken)
            ->withHeaders($headers)
            ->post('https://api.dropboxapi.com/2/files/list_folder/continue', [
                'cursor' => $cursor,
            ]);

        if ($response->failed()) {
            throw new \Exception("Dropbox list_folder/continue failed: " . $response->body());
        }

        $result = $response->json();

        return [
            'entries'  => $result['entries'] ?? [],
            'cursor'   => $result['cursor'] ?? null,
            'has_more' => $result['has_more'] ?? false,
        ];
    }

    public function createFolder($namespaceId, $path)
    {
        $headers = [
            'Dropbox-API-Path-Root' => json_encode([
                '.tag'         => 'namespace_id',
                'namespace_id' => $namespaceId
            ]),
            'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'),
        ];

        $response = Http::withToken($this->accessToken)
            ->withHeaders($headers)
            ->post('https://api.dropboxapi.com/2/files/create_folder_v2', [
                'path'       => $path,
                'autorename' => false
            ]);

        if ($response->failed()) {
            throw new \Exception("Dropbox create_folder_v2 failed: " . $response->body());
        }

        return $response->json();
    }

    private function getMemberAccessToken()
    {
        $response = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => config('services.dropbox.refresh_token'),
            'client_id'     => config('services.dropbox.app_key'),
            'client_secret' => config('services.dropbox.app_secret'),
        ]);

        if ($response->failed()) {
            throw new \Exception("Failed to refresh Dropbox member token: " . $response->body());
        }

        return $response->json()['access_token'];
    }

    public function upload($namespaceId, $path, $file)
    {
        $stream = fopen($file->getRealPath(), 'rb');
        $accessToken = $this->getMemberAccessToken();

        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Dropbox-API-Arg' => json_encode([
                    'path'           => $path,
                    'mode'           => 'add',
                    'autorename'     => true,
                    'mute'           => false,
                    'strict_conflict'=> false,
                ], JSON_UNESCAPED_SLASHES),
                'Dropbox-API-Path-Root' => json_encode([
                    '.tag'         => 'namespace_id',
                    'namespace_id' => $namespaceId,
                ]),
                'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'),
                'Content-Type'            => 'application/octet-stream',
            ])
            ->send('POST', 'https://content.dropboxapi.com/2/files/upload', [
                'body' => $stream,
            ]);

        fclose($stream);

        if ($response->failed()) {
            Log::error('Dropbox upload failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception("Dropbox upload failed: " . $response->body());
        }

        Log::info('Dropbox upload success', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return $response->json();
    }

    public function listTeamMembers()
    {
        $response = Http::withToken($this->accessToken)
            ->post('https://api.dropboxapi.com/2/team/members/list', [
                'limit' => 100
            ]);

        if ($response->failed()) {
            throw new \Exception("Dropbox members ophalen mislukt: " . $response->body());
        }

        return $response->json();
    }

    public function searchFoldersInNamespace($namespaceId, $path, $search)
    {
        $headers = [
            'Dropbox-API-Path-Root' => json_encode([
                '.tag'         => 'namespace_id',
                'namespace_id' => $namespaceId
            ]),
            'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'),
        ];

        $response = Http::withToken($this->accessToken)
            ->withHeaders($headers)
            ->post('https://api.dropboxapi.com/2/files/search_v2', [
                'query' => $search,
                'options' => [
                    'path' => $path,
                    'max_results' => 100,
                ]
            ]);

        if ($response->failed()) {
            throw new \Exception("Dropbox search_v2 failed: " . $response->body());
        }

        $matches = $response->json()['matches'] ?? [];

        return collect($matches)
            ->map(function ($m) use ($namespaceId) {
                $meta = $m['metadata']['metadata'] ?? null;
                if (!$meta || ($meta['.tag'] ?? null) !== 'folder') return null;

                return [
                    'name'      => $meta['name'],
                    'path'      => $meta['path_display'] ?? null,
                    'id'        => $meta['id'] ?? null,
                    'namespace' => $namespaceId,
                    'tag'       => $meta['.tag'] ?? 'unknown',
                ];
            })
            ->filter()
            ->values();
    }

    public function createChildFolder(string $namespaceId, string $parentPath, string $folderName): array
    {
        $safeName = trim(preg_replace('/[\/\\\\]+/', '-', $folderName));
        $fullPath = rtrim($parentPath, '/') . '/' . $safeName;

        $headers = [
            'Dropbox-API-Path-Root' => json_encode([
                '.tag'         => 'namespace_id',
                'namespace_id' => $namespaceId
            ]),
            'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'),
            'Content-Type'            => 'application/json',
        ];

        $res = Http::withToken($this->accessToken)
            ->withHeaders($headers)
            ->post('https://api.dropboxapi.com/2/files/create_folder_v2', [
                'path'       => $fullPath,
                'autorename' => false,
            ]);

        if ($res->failed()) {
            throw new \Exception("create_folder_v2 failed: " . $res->body());
        }

        return $res->json()['metadata'] ?? [
            '.tag'        => 'folder',
            'name'        => $safeName,
            'path_display'=> $fullPath,
        ];
    }

    public function getTemporaryLink(string $namespaceId, string $path)
    {
        $headers = [
            'Dropbox-API-Path-Root' => json_encode([
                '.tag'         => 'namespace_id',
                'namespace_id' => $namespaceId
            ]),
            'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'),
        ];

        $response = Http::withToken($this->accessToken)
            ->withHeaders($headers)
            ->post('https://api.dropboxapi.com/2/files/get_temporary_link', [
                'path' => $path,
            ]);

        if ($response->failed()) {
            throw new \Exception("Dropbox temporary link failed: " . $response->body());
        }

        return $response->json()['link'] ?? null;
    }
}
