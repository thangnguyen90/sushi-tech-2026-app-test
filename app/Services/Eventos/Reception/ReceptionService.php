<?php

namespace App\Services\Eventos\Reception;

use App\Services\Eventos\Api\ApiResource;
use Exception;
use Illuminate\Validation\ValidationException;

class ReceptionService
{
    private string $baseUrl;

    private array $cookies;

    /**
     * ReceptionService constructor.
     */
    public function __construct(string $baseUrl, array $cookies)
    {
        $this->baseUrl = $baseUrl;
        $this->cookies = $cookies;
    }

    /**
     * Get the list of reception settings for a specific event.
     *
     * @param  array  $eventInfo  Must contain 'portal' and 'event' keys.
     * @return array The list of reception settings.
     *
     * @throws ValidationException|Exception
     */
    public function listSettings(array $eventInfo): array
    {
        $this->_validateEventInfo($eventInfo);
        ['portal' => $portal, 'event' => $event] = $eventInfo;

        $client = $this->_createApiClient();
        $client->setMethod('GET');
        $client->setEndpoint("/console/api/v2/reception/setting/$portal/$event/list");

        $response = $client->send();

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        }

        throw new Exception('Failed to list reception settings. Status: '.$response->getStatusCode());
    }

    /**
     * Create a new reception setting.
     *
     * @throws ValidationException|Exception
     */
    public function createSetting(array $eventInfo, array $body): array
    {
        $this->_validateEventInfo($eventInfo);
        ['portal' => $portal, 'event' => $event] = $eventInfo;

        $client = $this->_createApiClient();
        $client->setMethod('POST');
        $client->setBody($body);
        $client->setEndpoint("/console/api/v2/reception/setting/$portal/$event/create");

        $response = $client->send();

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        }

        throw new Exception('Failed to create reception setting. Status: '.$response->getStatusCode());
    }

    /**
     * Update an existing reception setting.
     *
     * @throws ValidationException|Exception
     */
    public function updateSetting(array $eventInfo, int|string $settingId, array $body): array
    {
        $this->_validateEventInfo($eventInfo);
        ['portal' => $portal, 'event' => $event] = $eventInfo;

        $client = $this->_createApiClient();
        $client->setMethod('POST');
        $client->setBody($body);
        $client->setEndpoint("/console/api/v2/reception/setting/$portal/$event/$settingId/update");

        $response = $client->send();

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        }

        throw new Exception('Failed to update reception setting. Status: '.$response->getStatusCode());
    }

    /**
     * Creates a new API client instance for a request.
     */
    private function _createApiClient(): ApiResource
    {
        $client = new class($this->baseUrl) extends ApiResource {};
        $client->setHeaders(['Accept' => 'application/json']);
        $client->setCookies($this->cookies);

        return $client;
    }

    /**
     * Validates that the eventInfo array contains the required keys.
     *
     * @throws ValidationException
     */
    private function _validateEventInfo(array $eventInfo): void
    {
        if (! isset($eventInfo['portal'], $eventInfo['event'])) {
            throw ValidationException::withMessages([
                'eventInfo' => 'The eventInfo array must contain "portal" and "event" keys.',
            ]);
        }
    }
}
