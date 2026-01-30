<?php

namespace App\Services\Eventos\ReleaseContent;

use App\Services\Eventos\Api\ApiResource;
use App\Services\Eventos\EventosClient;
use Exception;
use Illuminate\Validation\ValidationException;

class ReleaseBoothService extends EventosClient
{
    public function __construct()
    {
        parent::__construct('Private'); // Ensure we are using private access
    }

    /**
     * @throws Exception|ValidationException
     */
    public function releaseBooth(int $boothContentId)
    {
        // 1. Retrieve and validate private event info
        $info = $this->getPrivateInfo();
        $this->validateEventInfo($info);
        ['portal' => $portal, 'event' => $event, 'booth' => $booth] = $info;

        try {
            // 2. Configure the API client for JSON request
            $api = new class($this->getBaseUrl()) extends ApiResource {};
            $api->setCookies($this->getCookies())
                ->setMethod('POST')
                ->setEndpoint("/console/api/v2/promote/booth/$portal/$event/$booth/$boothContentId")
                ->setHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);

            // 3. Send request, decode JSON, or throw on error
            $response = $api->send();
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }

            throw new Exception('POST failed, HTTP '.$response->getStatusCode());
        } catch (Exception $e) {
            // Handle specific exceptions if needed
            throw new Exception('Error releasing booth: '.$e->getMessage(), 0, $e);
        }

    }

    private function validateEventInfo(array $info): void
    {
        if (empty($info['portal']) || empty($info['event'])) {
            throw new ValidationException('Invalid booth information');
        }
    }
}
