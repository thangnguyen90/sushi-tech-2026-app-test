<?php

namespace App\Services\Eventos\Ticket;

use App\Services\Eventos\EventosClient;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebTicketService extends EventosClient
{
    const STATUS = 'web_api';
    public function __construct()
    {
        parent::__construct(self::STATUS);
    }

    /**
     * Fetches a list of ticket.
     *
     * @param int $categoryId
     * @param string $lang
     * @param int $page
     * @param int $perPage
     * @return array
     * @throws GuzzleException
     * @throws \Throwable
     */

    public function getTicketList(int $categoryId, string $lang='jpn'): array
    {
        // Retrieve and validate private event info
        $ticket = $this->moduleTicketId;
        $portal = $this->portal;
        $event = $this->event;
        // Configure API client for JSON request
        try {
            $key = 'web_ticket_list_' . $categoryId . '_lang_' . $lang;
            $isCached= false;
            if(Cache::getDefaultDriver() === 'redis'){
                $isCached = true;
            }
            if($isCached && $cached = Cache::tags('ticket_cached')->get($key) ){
                return $cached;
            }
            $api = $this->createApiClient()
                ->setMethod('GET')
                ->setEndpoint("/web_api/v2/ticket/$portal/$event/$ticket")
                ->setQueryParams([
//                    'page' => $page,
//                    'per_page' => $perPage,
                    'ticket_category_id' => $categoryId,
                    'ticket_category_type' => 'Small',
                ])
                ->setHeaders('Language', $lang);

            // Send request and decode JSON response
            $response = $api->send();
            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR)['data'] ?? [];
                if($isCached){
                    Cache::tags('ticket_cached')->put($key, $data, 60 + random_int(0,3)); // Cache for 1 minutes
                }
                return $data;
            }
        } catch (Exception $e) {
            Log::error('Error fetching booths', ['error' => $e->getMessage()]);
            throw new \Exception('GET failed: ' . $e->getMessage());
        }

        throw new \Exception('GET failed, HTTP ' . $response->getStatusCode());
    }
}
