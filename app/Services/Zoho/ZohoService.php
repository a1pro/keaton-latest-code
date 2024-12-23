<?php

namespace App\Services\Zoho;

use Illuminate\Support\Facades\Http;

class ZohoService
{
    private $clientId;
    private $clientSecret;
    private $refreshToken;

    public function __construct()
    {
        $this->clientId = env('ZOHO_CLIENT_ID');
        $this->clientSecret = env('ZOHO_CLIENT_SECRET');
        $this->refreshToken = env('ZOHO_REFRESH_TOKEN');
    }

    /**
     * Renew Access Token using the Refresh Token
     */
    public function refreshAccessToken()
    {
        $response = Http::asForm()->post('https://accounts.zoho.com/oauth/v2/token', [
            'refresh_token' => $this->refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->successful()) {
            $newAccessToken = $response['access_token'];
            
            cache(['zoho_access_token' => $newAccessToken], now()->addMinutes(55));

            return $newAccessToken;
        } else {
            throw new \Exception('Error refreshing Zoho Access Token: ' . $response->body());
        }
    }

    public function getAccessToken()
    {
        if (cache()->has('zoho_access_token')) {
            return cache('zoho_access_token');
        }

        return $this->refreshAccessToken();
    }

    public function findLeadByEmail($email)
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->get('https://www.zohoapis.com/crm/v2/Leads/search', [
            'criteria' => "(Email:equals:$email)"
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return !empty($data['data']) ? $data['data'][0] : null; // Retorna el lead encontrado o null
        } else {
            throw new \Exception('Error searching for lead in Zoho CRM: ' . $response->body());
        }
    }

    /**
     * Create a Lead in Zoho CRM
     */
    public function createLead($featureName, $email, $firstName, $lastName)
    {
        $accessToken = $this->getAccessToken();

        $leadSource = 'Website-' . ucfirst($featureName);

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post('https://www.zohoapis.com/crm/v2/Leads', [
            'data' => [
                [
                    'First_Name' => $firstName,
                    'Last_Name' => $lastName ? $lastName : 'Unknown',
                    'Email' => $email,
                    'Lead_Source' => $leadSource
                ]
            ]
        ]);

        if ($response->successful()) {
            return $response->json();
        } else {
            throw new \Exception('Error creating lead in Zoho CRM: ' . $response->body());
        }
    }
}
