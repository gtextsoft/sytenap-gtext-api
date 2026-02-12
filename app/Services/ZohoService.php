<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
 use App\Models\ZohoCredential;

class ZohoService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;
    protected string $apiDomain;

    public function __construct()
    {
        $this->clientId = env('ZOHO_CLIENT_ID');
        $this->clientSecret = env('ZOHO_CLIENT_SECRET');
        $this->redirectUri = env('ZOHO_REDIRECT_URI');
        $this->apiDomain = 'https://www.zohoapis.com'; // adjust if using EU/IN
    }

    /**
     * Exchange authorization code for tokens (one-time)
     */
    public function getTokensFromCode(string $code): array
    {
        $response = Http::asForm()->post('https://accounts.zoho.com/oauth/v2/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ]);

        return $response->json();
    }

    /**
     * Refresh access token using stored refresh token
     */
    public function refreshAccessToken(string $refreshToken): string
    {
        $response = Http::asForm()->post('https://accounts.zoho.com/oauth/v2/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        $data = $response->json();

        if (!isset($data['access_token'])) {
            throw new \Exception('Failed to refresh Zoho access token: ' . json_encode($data));
        }

        // Optionally cache for token lifetime
        Cache::put('zoho_access_token', $data['access_token'], now()->addSeconds($data['expires_in'] - 60));

        return $data['access_token'];
    }

   

    public function getAccessToken(): string
    {
        if (Cache::has('zoho_access_token')) {
            return Cache::get('zoho_access_token');
        }

        $credential = ZohoCredential::first();

        if (!$credential) {
            throw new \Exception('Zoho not connected. Run authorization first.');
        }

        return $this->refreshAccessToken($credential->refresh_token);
    }


    public function createDeal(array $dealData): array
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post($this->apiDomain . '/crm/v2/Deals', [
                'data' => [$dealData]
            ]);

        return $response->json();
    }


    /**
     * Optionally, create a contact if not exists and return its ID
     */
    public function createContact(array $contactData, string $refreshToken): string
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post($this->apiDomain . '/crm/v2/Contacts', [
                'data' => [$contactData]
            ]);

        $resp = $response->json();

        return $resp['data'][0]['id'] ?? '';
    }
}
