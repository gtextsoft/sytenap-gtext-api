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


    public function createDeal(array $dealData, string $contactId): array
    {
        $accessToken = $this->getAccessToken();

            // Ensure the contact ID is passed correctly
        $dealData['Contact_Name'] = ['id' => $contactId];

        $response = Http::withToken($accessToken)
            ->post($this->apiDomain . '/crm/v2/Deals', [
                'data' => [$dealData]
            ]);

        $resp = $response->json();

        if (isset($resp['data'][0]['code']) && $resp['data'][0]['code'] === 'INVALID_DATA') {
            throw new \Exception('Failed to create Zoho deal: ' . json_encode($resp));
        }

        return $resp;
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

        // Check for success
        if (!isset($resp['data'][0]['id'])) {
            throw new \Exception('Failed to create Zoho contact: ' . json_encode($resp));
        }

        return $resp['data'][0]['id']; // This is the numeric Zoho ID
    }

    public function getOrCreateContact(array $contactData, string $refreshToken): string
    {
        $accessToken = $this->getAccessToken();

        // Search contact by email
        $response = Http::withToken($accessToken)
            ->get($this->apiDomain . '/crm/v2/Contacts/search', [
                'email' => $contactData['Email']
            ]);

        $resp = $response->json();

        // If contact exists, return existing ID
        if (!empty($resp['data'][0]['id'])) {
            return $resp['data'][0]['id'];
        }

        // Otherwise, create new contact
        $response = Http::withToken($accessToken)
            ->post($this->apiDomain . '/crm/v2/Contacts', [
                'data' => [$contactData]
            ]);

        $resp = $response->json();

        if (!isset($resp['data'][0]['id'])) {
            throw new \Exception('Failed to create Zoho contact: ' . json_encode($resp));
        }

        return $resp['data'][0]['id'];
    }

    public function getOrCreateClient(array $clientData, string $refreshToken): string
    {
        $accessToken = $this->getAccessToken();
        $moduleName = 'Clients'; 

        // Search client by email (or any unique field)
        $response = Http::withToken($accessToken)
            ->get($this->apiDomain . "/crm/v2/{$moduleName}/search", [
                'email' => $clientData['Email'] // or use 'Company' if you want company name
            ]);

        $resp = $response->json();

        // If client exists, return existing ID
        if (!empty($resp['data'][0]['id'])) {
            return $resp['data'][0]['id'];
        }

        // Otherwise, create new client
        $response = Http::withToken($accessToken)
            ->post($this->apiDomain . "/crm/v2/{$moduleName}", [
                'data' => [$clientData]
            ]);

        $resp = $response->json();

        if (!isset($resp['data'][0]['id'])) {
            throw new \Exception('Failed to create Zoho client: ' . json_encode($resp));
        }

        return $resp['data'][0]['id'];
    }


}
