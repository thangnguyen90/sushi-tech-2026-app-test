<?php

namespace App\Services\Eventos\Booth;

use App\Services\Eventos\Api\ApiResource;
use App\Services\Eventos\EventosClient;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Log;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

/*
 * ListBoothService
 * This service is intended to handle listing booths in the Eventos system.
 * use it to fetch booth data by web API(web_api).
 */
class ListBoothService extends EventosClient
{

    const STATUS = 'web_api';
    public function __construct()
    {
        parent::__construct(self::STATUS);
    }
    /**
     * Fetches a list of booths.
     *
     * @return array
     * @throws \Exception
     */

    public function getBooths(int $booth, int $page = 1, int $perPage = 25 ): array
    {
        // Retrieve and validate private event info
        $portal = $this->portal;
        $event = $this->event;
        // Configure API client for JSON request
        try {
            $api = $this->createApiClient()
                ->setMethod('GET')
                ->setEndpoint("/web_api/v2/booth/$portal/$event/$booth")
                ->setQueryParams([
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

            // Send request and decode JSON response
            $response = $api->send();
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true)['data'] ?? [];
            }
        } catch (Exception $e) {
            Log::error('Error fetching booths', ['error' => $e->getMessage()]);
            throw new \Exception('GET failed: ' . $e->getMessage());
        }

        throw new \Exception('GET failed, HTTP '.$response->getStatusCode());
    }

    public function getBoothDetails(int $booth, $language, int $page = 1, int $perPage = 25 ): array
    {
        // Retrieve and validate private event info
        $portal = $this->portal;
        $event = $this->event;
        // Configure API client for JSON request
        try {
            $api = $this->createApiClient()
                ->setMethod('GET')
                ->setEndpoint("/web_api/v2/booth/$portal/$event/$booth?booth_ids=&is_detail=1")
                ->setQueryParams([
                    'page' => $page,
                    'per_page' => $perPage,
                ])
                ->setHeaders(['Language' => $language]);

            // Send request and decode JSON response
            $response = $api->send();
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true)['data'] ?? [];
            }
        } catch (Exception $e) {
            Log::error('Error fetching booths', ['error' => $e->getMessage()]);
            throw new \Exception('GET failed: ' . $e->getMessage());
        }

        throw new \Exception('GET failed, HTTP '.$response->getStatusCode());
    }

    /**
     * Fetches async a list of booths by module.
     *
     * @param array $tours
     * @param string $lang
     * @return array
     * @throws Exception
     */
    public function generateBoothRequests(array $tours, string $lang='jpn'): array
    {
        $promises = [];
        foreach ($tours as $module => $booths) {
            foreach (array_chunk($booths, 25) as $boothChunk) {
                $api = $this->createApiClient()
                    ->setMethod('GET')
                    ->setEndpoint("/web_api/v2/booth/{$this->portal}/{$this->event}/$module")
                    ->setQueryParams(['is_detail' => 1])
                    ->setHeaders('Language', $lang)
                    ->setRawQuery('booth_ids=[' . implode(',', $boothChunk) . ']');

                try {
                    $promises[] = $api->sendAsync();
                } catch (GuzzleException|\Throwable $e) {
                    Log::error('Error generating booth requests', [
                        'module' => $module,
                        'boothChunk' => $boothChunk,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        return $promises;
    }
}
