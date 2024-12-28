<?php

namespace App\Services\Zoho;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

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
     * Refresh Access Token using the Refresh Token with retry logic.
     */
    public function refreshAccessToken()
    {
        $retryCount = 0;
        $maxRetries = 5;
        $waitTime = 1; // Start with 1 second wait time.

        while ($retryCount < $maxRetries) {
            $response = Http::asForm()->post('https://accounts.zoho.com/oauth/v2/token', [
                'refresh_token' => $this->refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->successful()) {
                $newAccessToken = $response['access_token'];
                // Store the new access token in cache to use for future requests
                cache(['zoho_access_token' => $newAccessToken], now()->addMinutes(55));
                return $newAccessToken;
            }

            // If rate-limited, we wait and retry
            if ($response->status() == 429) {
                $retryCount++;
                $waitTime *= 2; // Exponential backoff
                sleep($waitTime); // Wait before retrying
            } else {
                // Handle other errors (like invalid credentials, etc.)
                throw new Exception('Error refreshing Zoho Access Token: ' . $response->body());
            }
        }

        // If max retries reached and still failed, throw an exception
        throw new Exception('Exceeded maximum retries to refresh Zoho Access Token');
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
        
        

        if ($response) {
            
            return $response->json();
        } else {
            throw new \Exception('Error creating lead in Zoho CRM: ' . $response->body());
        }
    }

public function createLead_new($featureName, $email, $firstName, $lastName)
{
    // Debugging output for parameters
    \Log::info("Feature Name: $featureName, Email: $email, First Name: $firstName, last Name: $lastName");

    $attempts = 0;
    $maxAttempts = 5; // Maximum number of retry attempts
    $backoff = 1; // Initial backoff time in seconds

    while ($attempts < $maxAttempts) {
        try {
            // Get access token
           echo $accessToken = $this->getAccessToken();

            // Set the lead source
            $leadSource = 'Website-' . ucfirst($featureName);

            // Make the HTTP POST request to Zoho CRM
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

  echo"sucess";
                echo "<pre>";
print_r($response);
$responseBody = $response->json(); // Laravel's Http Client method
print_r($responseBody);

die();
            // If the request is successful, return the response
            if ($response->successful()) {
                return $response->json();
              
            }

            // If the rate limit error occurs, throw an exception
            if ($response->status() == 429) {
                \Log::warning("Rate limit exceeded. Retrying... Attempt: $attempts");
                echo"failed";
                echo "<pre>";
print_r($response);

die();
                $attempts++;
                sleep($backoff); // Wait before retrying
                $backoff *= 2; // Exponential backoff
                continue;
            }

            // If the response is not successful, throw an exception
            throw new \Exception('Error creating lead in Zoho CRM: ' . $response->body());

        } catch (\Exception $e) {
            \Log::error('Error creating lead: ' . $e->getMessage());
            throw $e; // Rethrow the exception if retry limit is reached or another error occurs
        }
    }

    // If we reach here, the retry attempts were exhausted
    throw new \Exception('Maximum retry attempts reached while creating Zoho lead.');
}


}
