<?php

namespace App\Services\Eventos\Qr;

use App\Services\Eventos\Api\ApiResource;
use Exception;

final class QrService
{
    private string $baseUrl;

    private array $cookies;

    private string $systemBaseUrl;

    public function __construct(string $baseUrl, array $cookies)
    {
        $this->baseUrl = $baseUrl;
        $this->cookies = $cookies;
        $this->systemBaseUrl = config('eventos.systemBaseUrl');
    }

    /**
     * Handles a staff login via QR code data.
     *
     * @throws Exception
     */
    public function storeLogin(array $body): array
    {
        $client = new class($this->systemBaseUrl) extends ApiResource {};
        $client->setMethod('POST')->setBody($body)->setEndpoint('/api/v2/staff/login');

        return $this->_sendAndDecode($client, 'Failed to store QR login.');
    }

    /**
     * Stores a QR code check-in.
     *
     * @throws Exception
     */
    public function storeCheckin(array $eventInfo, int|string $receptionId, array $body, array $headerToken): array
    {
        // Validation logic can be added here
        ['event' => $event] = $eventInfo;

        $client = new class($this->baseUrl) extends ApiResource {};
        $client->setMethod('POST')->setBody($body);
        $client->setEndpoint("/qrticketing/api/v2/checkin/mode/ticket/$event/$receptionId");
        $client->setHeaders(['Accept' => 'application/json', ...$headerToken]);
        $client->setCookies($this->cookies);

        return $this->_sendAndDecode($client, 'Failed to store QR check-in.');
    }

    /**
     * @throws Exception
     */
    private function _sendAndDecode(ApiResource $client, string $errorMessage): array
    {
        $response = $client->send();
        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true) ?? [];
        }
        throw new Exception($errorMessage.' Status: '.$response->getStatusCode());
    }
}
