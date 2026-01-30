<?php

namespace App\Services\Eventos\Booth;

use App\Services\Eventos\EventosClient;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CompanyBoothService extends EventosClient
{
    /**
     * @throws Exception|ValidationException|Throwable|GuzzleException
     */
    public function get(?int $eventosBoothId = null)
    {
        // Retrieve and validate private event info
        $info = $this->getPrivateInfo();
        $this->_validateEventInfo($info);
        ['portal' => $portal, 'event' => $event, 'booth' => $booth] = $info;
        // Configure API client for JSON request
        $api = $this->createApiClient();
        if ($eventosBoothId) {
            $api
                ->setCookies($this->getCookies())         // attach auth cookies
                ->setMethod('GET')                              // use HTTP POST
                ->setEndpoint("/console/api/booth/{$portal}/{$event}/{$booth}/{$eventosBoothId}?is_publish=false")
                ->setHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);
        } else {
            $api
                ->setCookies($this->getCookies())         // attach auth cookies
                ->setMethod('GET')                              // use HTTP POST
                ->setEndpoint("/console/api/booth/{$portal}/{$event}/{$booth}/initial?is_publish=false")
                ->setHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);
        }

        // Send request, decode JSON, or throw on error
        $response = $api->send();
        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true);
        }

        throw new Exception('GET failed, HTTP ' . $response->getStatusCode());
    }

    /**
     * @throws ValidationException
     */
    private function _validateEventInfo(array $info): void
    {
        foreach (['portal', 'event', 'booth'] as $key) {
            if (!isset($info[$key])) {
                throw ValidationException::withMessages([
                    'eventInfo' => "Missing key '{$key}' in eventInfo.",
                ]);
            }
        }
    }

    /**
     * @throws Exception|ValidationException|Throwable|GuzzleException
     */
    public function saveBooth(array $params, ?int $eventosBoothId = null): array
    {
        // Retrieve and validate private event info
        $info = $this->getPrivateInfo();
        $this->_validateEventInfo($info);
        ['portal' => $portal, 'event' => $event, 'booth' => $booth] = $info;

        $api = $this->createApiClient();
        if ($eventosBoothId) {
            $api->setCookies($this->getCookies())
                ->setMethod('POST')
                ->setEndpoint("/console/api/booth/{$portal}/{$event}/{$booth}/{$eventosBoothId}")
                ->setHeaders(['Accept' => 'application/json'])
                ->setBody($params);
        } else {
            $api->setCookies($this->getCookies())
                ->setMethod('POST')
                ->setEndpoint("/console/api/booth/{$portal}/{$event}/{$booth}")
                ->setHeaders(['Accept' => 'application/json'])
                ->setBody($params);
        }

        // Send request, decode JSON, or throw on error
        $response = $api->send();
        if (in_array($response->getStatusCode(), [200, 204], true)) {
            $body = $response->getBody()->getContents();

            if ($response->getStatusCode() === 204 || $body === '') {
                return [];
            }

            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Invalid JSON: ' . json_last_error_msg());
            }

            return $data;
        }

        throw new Exception('Create failed, HTTP ' . $response->getStatusCode());
    }

}
