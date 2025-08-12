<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class ApiService
{
    protected Client $client;
    protected string $baseUrl;
    protected array $headers;

    public function __construct()
    {
        $this->baseUrl = config('api.url');
        // $apiToken = env('API_TOKEN');

        $this->headers = [
            'Accept' => 'application/json',
            // 'Authorization' => 'Bearer ' . $apiToken,
        ];

        $this->client = new Client();
    }

    /**
     * Makes a GET request to the specified API endpoint.
     *
     * @param string $endpoint The API endpoint, e.g., '/api/menus/with-modifiers'
     * @param array $query The query parameters to pass with the request
     * @return array|null The decoded JSON response or null on failure
     */
    public function get(string $endpoint, array $query = []): ?array
    {
        try {
            $response = $this->client->get($this->baseUrl . $endpoint, [
                'headers' => $this->headers,
                'query' => $query,
            ]);

            // Check for a successful status code
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true);
            }

            Log::warning("API call to {$endpoint} failed with status code: {$response->getStatusCode()}");
            return null;

        } catch (GuzzleException $e) {
            // Catch Guzzle exceptions (network errors, timeouts, 4xx/5xx status codes)
            Log::error("API call to {$endpoint} failed: " . $e->getMessage());
            return null;
        }
    }
}