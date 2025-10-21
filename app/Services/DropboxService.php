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
            throw new \Exception("Dropbox token refresh failed: " . $response->json()['error_summary'] ?? $response->body());
        }

        return $response->json()['access_token'];
    }

    public function listNamespaces()
    {
        $response = Http::withToken($this->accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('https://api.dropboxapi.com/2/team/namespaces/list', (object)[]);

        if ($response->failed()) {
            throw new \Exception("Dropbox namespaces ophalen mislukt: " . $response->json()['error_summary'] ?? $response->body());
        }

        return $response->json()['namespaces'] ?? [];
    }

    public function getFluviusNamespaceId()
    {
        $namespaces = $this->listNamespaces();

        if (empty($namespaces)) {
            throw new \Exception("Geen namespaces gevonden! Response leeg.");
        }

        $fluvius = collect($namespaces)
            ->first(fn($ns) => stripos($ns['name'], 'fluvius aansluitingen') !== false);

        if (!$fluvius) {
            throw new \Exception("Fluvius Aansluitingen namespace niet gevonden.");
        }

        return $fluvius['namespace_id'];
    }

   public function listFoldersInNamespace($namespaceId, $path = "")
{
    if ($path === null || strtolower($path) === 'null') {
        $path = ""; // forceer root in namespace
    }

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
        throw new \Exception("Dropbox list_folder failed: " . ($response->json()['error_summary'] ?? $response->body()));
    }

    $result = $response->json();

    return [
        'entries'  => $result['entries'] ?? [],
        'cursor'   => $result['cursor'] ?? null,
        'has_more' => $result['has_more'] ?? false,
    ];
}

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
            throw new \Exception("Dropbox list_folder/continue failed: " . $response->json()['error_summary'] ?? $response->body());
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
            throw new \Exception("Dropbox create_folder_v2 failed: " . $response->json()['error_summary'] ?? $response->body());
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
            throw new \Exception("Failed to refresh Dropbox member token: " . $response->json()['error_summary'] ?? $response->body());
        }

        return $response->json()['access_token'];
    }

 public function upload($namespaceId, $path, $file)
{
    $accessToken = $this->getMemberAccessToken();

    // ✅ path komt nu altijd met bestandsnaam uit de controller
    $dropboxPath = '/' . ltrim($path, '/');

    $headers = [
        'Dropbox-API-Path-Root' => json_encode([
            '.tag'         => 'namespace_id',
            'namespace_id' => $namespaceId,
        ]),
        'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'),
        'Content-Type'            => 'application/octet-stream',
    ];

    $attempts = 0;
    $maxAttempts = 3;
    $stream = fopen($file->getRealPath(), 'rb');

    try {
        do {
            $attempts++;

            $res = Http::withToken($accessToken)
                ->withHeaders(array_merge($headers, [
                    'Dropbox-API-Arg' => json_encode([
                        'path'           => $dropboxPath,
                        'mode'           => 'add',
                        'autorename'     => true,
                        'mute'           => false,
                        'strict_conflict'=> false,
                    ], JSON_UNESCAPED_SLASHES),
                ]))
                ->send('POST', 'https://content.dropboxapi.com/2/files/upload', [
                    'body' => $stream,
                ]);

            if ($res->status() === 429 && $attempts < $maxAttempts) {
                $wait = $attempts * 2; 
                Log::warning("Dropbox upload rate limit (429), poging {$attempts}, wacht {$wait}s...");
                sleep($wait);
                continue;
            }

            if ($res->failed()) {
                Log::error('Dropbox upload failed', [
                    'status' => $res->status(),
                    'body'   => $res->body(),
                    'path'   => $dropboxPath,
                ]);
                throw new \Exception("Dropbox upload failed: " . $res->body());
            }

            Log::info('Dropbox upload success', [
                'status' => $res->status(),
                'path'   => $dropboxPath,
            ]);

            return $res->json();

        } while ($attempts < $maxAttempts);

    } finally {
        fclose($stream);
    }

    throw new \Exception("Dropbox upload kon niet worden uitgevoerd na {$maxAttempts} pogingen.");
}



    public function listTeamMembers()
    {
        $response = Http::withToken($this->accessToken)
            ->post('https://api.dropboxapi.com/2/team/members/list', [
                'limit' => 100
            ]);

        if ($response->failed()) {
            throw new \Exception("Dropbox members ophalen mislukt: " . $response->json()['error_summary'] ?? $response->body());
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
        throw new \Exception("Dropbox search_v2 failed: " . ($response->json()['error_summary'] ?? $response->body()));
    }

    $matches = $response->json()['matches'] ?? [];

    return collect($matches)
        ->map(function ($m) use ($namespaceId) {
            $meta = $m['metadata']['metadata'] ?? null;
            if (!$meta || ($meta['.tag'] ?? null) !== 'folder') return null;

            return [
                'name'      => $meta['name'],
                'path'      => $meta['path_display'] ?? "",   // ✅ altijd path_display
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
    $safeName = mb_substr($safeName, 0, 100); // extra bescherming

    // ✅ Altijd met leading slash, Dropbox verwacht dit
    $fullPath = '/' . ltrim(rtrim($parentPath, '/') . '/' . $safeName, '/');

    Log::info("createChildFolder → resolved path", [
        'namespaceId' => $namespaceId,
        'parentPath'  => $parentPath,
        'fullPath'    => $fullPath,
    ]);

    $headers = [
        'Dropbox-API-Path-Root' => json_encode([
            '.tag'         => 'namespace_id',
            'namespace_id' => $namespaceId
        ]),
        'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'),
        'Content-Type'            => 'application/json',
    ];

    // ✅ Retry mechanisme max 3 pogingen bij 429
    $attempts = 0;
    $maxAttempts = 3;

    do {
        $attempts++;

        $res = Http::withToken($this->accessToken)
            ->withHeaders($headers)
            ->post('https://api.dropboxapi.com/2/files/create_folder_v2', [
                'path'       => $fullPath,
                'autorename' => false,
            ]);

        if ($res->status() === 429 && $attempts < $maxAttempts) {
            $wait = $attempts * 2; // exponential backoff
            Log::warning("Dropbox rate limit (429), poging {$attempts}, wacht {$wait}s...");
            sleep($wait);
            continue; // probeer opnieuw
        }

        if ($res->failed()) {
            Log::error("Dropbox create_folder_v2 failed", [
                'status' => $res->status(),
                'body'   => $res->body(),
            ]);
            throw new \Exception("create_folder_v2 failed: " . ($res->json()['error_summary'] ?? $res->body()));
        }

        return $res->json()['metadata'] ?? [
            '.tag'         => 'folder',
            'name'         => $safeName,
            'path_display' => $fullPath,
        ];

    } while ($attempts < $maxAttempts);

    throw new \Exception("Dropbox create_folder_v2 kon niet worden uitgevoerd na {$maxAttempts} pogingen.");
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
            throw new \Exception("Dropbox temporary link failed: " . $response->json()['error_summary'] ?? $response->body());
        }

        return $response->json()['link'] ?? null;
    }

        /**
     * ============================================================
     * 🔹 DIRECT UPLOAD SESSIONS (voor client-side Dropbox uploads)
     * ============================================================
     */

    // Haal een vers access token op (frontend mag dit tijdelijk gebruiken)
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

  public function startUploadSession(): array
{
    $accessToken = $this->accessToken;

    $response = Http::withToken($accessToken)
        ->withHeaders([
            'Content-Type' => 'application/octet-stream',
            'Dropbox-API-Arg' => json_encode(['close' => false]),
            // 🔹 heel belangrijk bij Business accounts:
            'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'),
        ])
        ->send('POST', 'https://content.dropboxapi.com/2/files/upload_session/start', [
            'body' => '', // lege body
        ]);

    if ($response->failed()) {
        throw new \Exception(
            "Dropbox upload_session/start failed: " . ($response->body() ?: 'unknown error')
        );
    }

    return $response->json();
}



    // 2️⃣ Voeg chunk toe aan sessie
    public function appendToSession(string $sessionId, string $binaryChunk, int $offset): void
    {
        $accessToken = $this->accessToken;

        $res = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'application/octet-stream',
                'Dropbox-API-Arg' => json_encode([
                    'cursor' => [
                        'session_id' => $sessionId,
                        'offset'     => $offset
                    ],
                    'close' => false
                ])
            ])
            ->send('POST', 'https://content.dropboxapi.com/2/files/upload_session/append_v2', [
                'body' => $binaryChunk,
            ]);

        if ($res->failed()) {
            throw new \Exception("Dropbox append_v2 failed: " . ($res->body() ?: 'unknown error'));
        }
    }

    // 3️⃣ Sessie afronden en bestand opslaan
    public function finishUploadSession(string $sessionId, int $offset, string $targetPath): array
    {
        $accessToken = $this->accessToken;

        $res = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'application/octet-stream',
                'Dropbox-API-Arg' => json_encode([
                    'cursor' => [
                        'session_id' => $sessionId,
                        'offset'     => $offset
                    ],
                    'commit' => [
                        'path' => $targetPath,
                        'mode' => 'add',
                        'autorename' => true,
                        'mute' => false
                    ]
                ])
            ])
            ->send('POST', 'https://content.dropboxapi.com/2/files/upload_session/finish', [
                'body' => '',
            ]);

        if ($res->failed()) {
            throw new \Exception("Dropbox finish failed: " . ($res->body() ?: 'unknown error'));
        }

        return $res->json();
    }

    public function uploadStreamFast($namespaceId, $path, $file)
{
    $accessToken = $this->getMemberAccessToken();
    $client = new \GuzzleHttp\Client(['base_uri' => 'https://content.dropboxapi.com/2/']);

    $dropboxPath = '/' . ltrim($path, '/');

    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Dropbox-API-Select-User' => config('services.dropbox.team_member_id'),
        'Dropbox-API-Path-Root' => json_encode([
            '.tag' => 'namespace_id',
            'namespace_id' => $namespaceId
        ]),
        'Dropbox-API-Arg' => json_encode([
            'path' => $dropboxPath,
            'mode' => 'add',
            'autorename' => true,
            'mute' => false
        ]),
        'Content-Type' => 'application/octet-stream'
    ];

    $client->request('POST', 'files/upload', [
        'headers' => $headers,
        'body' => fopen($file->getRealPath(), 'r'),
        'timeout' => 120,
    ]);
}


}
