<?php

namespace App\Services\Eventos\Ticket;

use App\Services\Eventos\Api\ApiResource;
use App\Services\Eventos\EventosClient;
use Exception;

final class PublicTicketService extends EventosClient
{
    const string STATUS = 'Public';
    public function __construct()
    {
        parent::__construct(self::STATUS);
    }

    /**
     * Creates a new master ticket.
     *
     * @throws Exception
     */
    public function create(int $module, array $data): array
    {
        $client = $this->_createApiClient();
        $client->setMethod('POST')->setBody($data);
        $client->setEndpoint("/api/v1/ticket/normal/master/$module");

        return $this->_sendAndDecode($client, 'Failed to create ticket.');
    }

    /**
     * Updates an existing master ticket.
     *
     * @throws Exception
     */
    public function update(int $module, int $ticketId, array $data): array
    {
        $client = $this->_createApiClient();
        $client->setMethod('PUT')->setBody($data);
        $client->setEndpoint("/api/v1/ticket/normal/master/$module/$ticketId");

        return $this->_sendAndDecode($client, 'Failed to update ticket.');
    }

    /**
     * Partially updates a master ticket.
     *
     * @throws Exception
     */
    public function updatePartial(int $ticketId, array $data): array
    {
        $client = $this->_createApiClient();
        $client->setMethod('PUT')->setBody($data);
        $client->setEndpoint("/api/v1/ticket/normal/master/partial/$ticketId");

        return $this->_sendAndDecode($client, 'Failed to partially update ticket.');
    }

    /**
     * Deletes a master ticket.
     *
     * @throws Exception
     */
    public function delete(int $module, int $ticketId): array
    {
        $client = $this->_createApiClient();
        $client->setMethod('DELETE');
        $client->setEndpoint("/api/v1/ticket/normal/master/$module/$ticketId");

        return $this->_sendAndDecode($client, 'Failed to delete ticket.');
    }

    /**
     * Orders a normal ticket.
     *
     * @throws Exception
     */
    public function order( array $data): array
    {
        $client = $this->createApiClient();
        $client->setMethod('POST')->setBody($data);
        $client->setEndpoint("/api/v1/ticket/normal/order/$this->moduleTicketId");

        return $this->_sendAndDecode($client, 'Failed to order ticket.');
    }

    /**
     * get one a normal ticket data.
     *
     * @throws Exception
     */
    public function getTicketData(): array
    {
        $client = $this->createApiClient();
        $client->setMethod('GET');
        $client->setEndpoint("/api/v1/ticket/orderer_info/$this->moduleTicketId");

        return $this->_sendAndDecode($client, 'Failed to order ticket.');
    }

    /**
     * @throws Exception
     */
    public function cancel(string $user_uuid, array $data): array
    {
        $client = $this->createApiClient();
        $client->setMethod('DELETE');
        $client->setEndpoint("/api/v1/ticket/normal/order/$this->moduleTicketId/uuid/$user_uuid");
        $client->setBody($data);

        return $this->_sendAndDecode($client, 'Failed to order ticket.');
    }

    /**
     * Gets ticket order content by user UUID.
     *
     * @throws Exception
     */
    public function getContentByUser(int $module, string $userUuid): array
    {
        $client = $this->createApiClient();
        $client->setMethod('GET');
        $client->setEndpoint("/api/v1/ticket/normal/order/$this->moduleTicketId/uuid/$userUuid");

        return $this->_sendAndDecode($client, 'Failed to get ticket content.');
    }

    /**
     * Overwrites a ticket with an external QR code.
     *
     * @throws Exception
     */
    public function overwriteQr(int $ticketId, array $data): array
    {
        $client = $this->createApiClient();
        $client->setMethod('PUT')->setBody($data);
        $client->setEndpoint("/api/v1/ticket/normal/external_qr/$ticketId");

        return $this->_sendAndDecode($client, 'Failed to overwrite QR.');
    }

    /**
     * Creates a pre-configured API client instance.
     */
    private function _createApiClient(): ApiResource
    {
        $client = new class($this->publicUrl) extends ApiResource {};
        $client->setHeaders([
            'Accept' => 'application/json',
            'X-Api-Key' => $this->publicSecret,
            'token' => $this->publicToken,
        ]);

        return $client;
    }

    /**
     * Sends the request and decodes the JSON response.
     *
     * @throws Exception
     */
    private function _sendAndDecode(ApiResource $client, string $errorMessage): array
    {
        $response = $client->send();
        if ($response->getStatusCode() === 200) {
            $content = $response->getBody()->getContents();

            return json_decode($content, true) ?? [];
        }
        throw new Exception($errorMessage.' Status: '.$response->getStatusCode());
    }
}
