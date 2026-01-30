<?php

namespace App\Services\Eventos\Ticket;

use App\Services\Eventos\Api\ApiResource;
use Exception;
use Illuminate\Validation\ValidationException;

class TicketService
{
    private string $baseUrl;

    private array $cookies;

    /**
     * TicketService constructor.
     */
    public function __construct(string $baseUrl, array $cookies)
    {
        $this->baseUrl = $baseUrl;
        $this->cookies = $cookies;
    }

    /**
     * Creates a new ticket.
     *
     * @throws ValidationException|Exception
     */
    public function create(array $eventInfo, array $data): array
    {
        $this->_validateEventInfo($eventInfo, ['portal', 'event', 'ticket']);

        ['portal' => $portal, 'event' => $event, 'ticket' => $ticket] = $eventInfo;

        $client = $this->_createApiClient();
        $client->setMethod('POST');
        $client->setBody($data);
        $client->setEndpoint("/console/api/v2/ticket/$portal/$event/$ticket");

        $response = $client->send();

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        }

        throw new Exception('Failed to create ticket. Status: '.$response->getStatusCode());
    }

    /**
     * Updates an existing ticket.
     *
     * @throws ValidationException|Exception
     */
    public function update(array $eventInfo, array $data): array
    {
        if (empty($data)) {
            throw new Exception('Data for updating ticket cannot be empty.');
        }
        $this->_validateEventInfo($eventInfo, ['portal', 'event', 'module', 'ticket']);

        ['portal' => $portal, 'event' => $event, 'module' => $module, 'ticket' => $ticket] = $eventInfo;

        $client = $this->_createApiClient();
        $client->setMethod('POST');
        $client->setBody($data);
        $client->setEndpoint("/console/api/v2/ticket/$portal/$event/$module/$ticket");

        $response = $client->send();

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        }

        throw new Exception('Failed to update ticket. Status: '.$response->getStatusCode());
    }

    /**
     * Publishes (promotes) a ticket.
     *
     * @throws ValidationException|Exception
     */
    public function publish(array $eventInfo): array
    {
        $this->_validateEventInfo($eventInfo, ['portal', 'event', 'module', 'ticket']);

        ['portal' => $portal, 'event' => $event, 'module' => $module, 'ticket' => $ticket] = $eventInfo;

        $client = $this->_createApiClient();
        $client->setMethod('POST');
        $client->setEndpoint("/console/api/v2/promote/ticket/$portal/$event/$module/$ticket");

        $response = $client->send();

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        }

        throw new Exception('Failed to publish ticket. Status: '.$response->getStatusCode());
    }

    /**
     * Creates a new API client instance for a request.
     */
    private function _createApiClient(): ApiResource
    {
        $client = new class($this->baseUrl) extends ApiResource {};
        $client->setHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);
        $client->setCookies($this->cookies);

        return $client;
    }

    /**
     * Validates that the eventInfo array contains the required keys.
     *
     * @throws ValidationException
     */
    private function _validateEventInfo(array $eventInfo, array $requiredKeys): void
    {
        foreach ($requiredKeys as $key) {
            if (! isset($eventInfo[$key])) {
                throw ValidationException::withMessages([
                    'eventInfo' => "The eventInfo array must contain the '$key' key.",
                ]);
            }
        }
    }
}
