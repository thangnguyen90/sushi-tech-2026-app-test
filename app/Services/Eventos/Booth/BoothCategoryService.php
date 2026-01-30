<?php

namespace App\Services\Eventos\Booth;

use App\Services\Eventos\Api\ApiResource;
use App\Services\Eventos\EventosClient;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Validation\ValidationException;
use Psr\Http\Client\ClientExceptionInterface;

class BoothCategoryService extends EventosClient
{
    public function __construct()
    {
        parent::__construct('Private'); // Ensure we are using private access
    }

    /**
     * @throws Exception|ValidationException|GuzzleException
     * @throws ClientExceptionInterface
     */
    public function getCategories(): array
    {
        // Retrieve and validate private event info

        $info = $this->getPrivateInfo();
        $this->validateEventInfo($info);
        ['portal' => $portal, 'event' => $event] = $info;

        // Configure API client for JSON request
        $api = new class($this->getBaseUrl()) extends ApiResource {};
        $api->setCookies($this->getCookies())
            ->setMethod('GET')
            ->setEndpoint("/console/api/category/{$portal}/{$event}/namelist")
            ->setHeaders(['Accept' => 'application/json']);

        // Send request and decode JSON response
        $response = $api->send();
        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        }
        throw new Exception('GET failed, HTTP '.$response->getStatusCode());
    }

    /**
     * @throws ValidationException
     */
    private function validateEventInfo(array $info): void
    {
        if (empty($info['portal']) || empty($info['event'])) {
            throw new ValidationException('Invalid booth information');
        }
    }
}
