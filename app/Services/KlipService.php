<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class KlipService
{
    protected $baseUrl;
    protected $tokenUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.klip.base_url');
        $this->tokenUrl = config('services.klip.token_url');
    }

    /**
     * Haal een OAuth 2.0 access token op en cache deze.
     */
    public function getAccessToken()
    {
        // Cache het token voor een uur (of hoe lang de API aangeeft)
        return Cache::remember('klip_access_token', 3500, function () {
            $response = Http::asForm()->post($this->tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.klip.client_id'),
                'client_secret' => config('services.klip.client_secret'),
                'scope' => 'klip_api_scope' // Check de Athumi docs voor de exacte scope naam
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            throw new \Exception('Kan KLIP token niet ophalen: ' . $response->body());
        });
    }

    /**
     * Voorbeeld: Haal planaanvragen (MapRequests) op
     */
    public function getMapRequests()
    {
        $token = $this->getAccessToken();
        $orgId = config('services.klip.organisation_id');

        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/json',
                'organisationId' => $orgId
            ])
            ->get("{$this->baseUrl}/ws/klip/v3/maprequest");

        if ($response->failed()) {
            throw new \Exception('Fout bij ophalen KLIP MapRequests: ' . $response->body());
        }

        return $response->json();
    }
}