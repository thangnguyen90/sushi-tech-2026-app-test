<?php

namespace App\Services\Eventos\Booth;

use App\Services\Eventos\Api\ApiResource;
use App\Services\Eventos\EventosClient;
use Exception;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Validation\ValidationException;

class BoothImageService
{
    private EventosClient $eventos;

    public function __construct(EventosClient $eventos)
    {
        $this->eventos = $eventos;
    }

    /**
     * @throws Exception|ValidationException
     */
    public function upload(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new Exception("File not found: {$filePath}");
        }

        // Take portal/event/booth from EventosClient
        $info = $this->eventos->getPrivateInfo();
        $this->validateEventInfo($info);
        ['portal' => $portal, 'event' => $event, 'booth' => $booth] = $info;
        // Create client multipart
        $api = new class($this->eventos->getBaseUrl()) extends ApiResource {};
        $api->setCookies($this->eventos->getCookies())
            ->setMethod('POST')
            ->setEndpoint("/console/api/booth/{$portal}/{$event}/{$booth}/image")
            ->setHeaders(['Accept' => 'application/json'])
            ->setBody([
                'multipart' => [[
                    'name' => 'image',
                    'contents' => Utils::tryFopen($filePath, 'r'),
                    'filename' => basename($filePath),
                ]],
            ]);

        $response = $api->send();
        if ($response->getStatusCode() === 200) {
            $image = json_decode($response->getBody()->getContents(), true);
            $returnImg = $image['image'] ?? null;
            if (! empty($returnImg)) {
                $returnImg['path'] = $this->buildFullImageUrl($returnImg['file']);
            }

            return $returnImg;
        }

        throw new Exception('Upload failed, HTTP '.$response->getStatusCode());
    }

    /**
     * @throws ValidationException
     */
    private function validateEventInfo(array $info): void
    {
        foreach (['portal', 'event', 'booth'] as $key) {
            if (! isset($info[$key])) {
                throw ValidationException::withMessages([
                    'eventInfo' => "Missing key '{$key}' in eventInfo.",
                ]);
            }
        }
    }

    /**
     * Build a signed-looking EVENTOS temp image URL.
     * Format:
     *   {BASE}/images/{CLIENT_ID}/{PORTAL}/{EVENT}/temp/{BOOTH_ID}-{UUID}.{ext}
     */
    public function buildFullImageUrl(string $path): string
    {
        $baseUrl = rtrim(getenv('EVENTOS_PRIVATE_BASE_URL'), '/').'/';
        $clientId = getenv('EVENTOS_CLIENT_ID');
        $portal = getenv('EVENTOS_PORTAL');
        $event = getenv('EVENTOS_EVENT');

        if (! $baseUrl || ! $clientId || ! $portal || ! $event || ! $path) {
            throw new \RuntimeException('Missing required EVENTOS_* env variables or $path');
        }

        return $baseUrl."images/{$clientId}/{$portal}/{$event}/".ltrim($path, '/');
    }
}
