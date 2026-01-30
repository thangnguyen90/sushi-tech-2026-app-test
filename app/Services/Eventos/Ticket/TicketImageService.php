<?php

namespace App\Services\Eventos\Ticket;

use App\Services\Eventos\Api\ApiResource;
use Exception;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Validation\ValidationException;

class TicketImageService
{
    private string $baseUrl;

    private array $cookies;

    /**
     * TicketImageService constructor.
     */
    public function __construct(string $baseUrl, array $cookies)
    {
        $this->baseUrl = $baseUrl;
        $this->cookies = $cookies;
    }

    /**
     * Uploads an image for a specific ticket.
     *
     * @param  string  $filePath  The local path to the image file.
     * @param  array  $eventInfo  Must contain 'portal', 'event', and 'ticket' keys.
     * @return array The API response.
     *
     * @throws ValidationException|Exception
     */
    public function upload(string $filePath, array $eventInfo): array
    {
        if (! file_exists($filePath)) {
            throw new Exception("File not found at path: {$filePath}");
        }
        $this->_validateEventInfo($eventInfo);

        ['portal' => $portal, 'event' => $event, 'ticket' => $ticket] = $eventInfo;

        // Create a temporary client for this upload action
        $client = $this->_createApiClient();

        $client->setMethod('POST');
        $client->setEndpoint("/console/api/v2/{$portal}/{$event}/image/{$ticket}");

        // Prepare the multipart body for file upload
        $multipartData = [
            [
                'name' => 'image',
                'contents' => Utils::tryFopen($filePath, 'r'),
                'filename' => basename($filePath),
            ],
        ];
        $client->setBody(['multipart' => $multipartData]);

        // The logic to send the request was missing in your original file.
        // It is now added here.
        $response = $client->send();

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        }

        throw new Exception('Failed to upload ticket image. Status: '.$response->getStatusCode());
    }

    private function _createApiClient(): ApiResource
    {
        $client = new class($this->baseUrl) extends ApiResource {};
        // No Content-Type header needed for multipart, Guzzle handles it.
        $client->setHeaders(['Accept' => 'application/json']);
        $client->setCookies($this->cookies);

        return $client;
    }

    private function _validateEventInfo(array $eventInfo): void
    {
        $requiredKeys = ['portal', 'event', 'ticket'];
        foreach ($requiredKeys as $key) {
            if (! isset($eventInfo[$key])) {
                throw ValidationException::withMessages([
                    'eventInfo' => "The eventInfo array must contain the '{$key}' key.",
                ]);
            }
        }
    }
}
