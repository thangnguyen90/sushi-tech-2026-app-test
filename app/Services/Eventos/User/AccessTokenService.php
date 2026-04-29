<?php

namespace App\Services\Eventos\User;

use App\Exceptions\UnauthorizedException;
use App\Models\MatchingCsvDownloadSetting;
use App\Services\Eventos\EventosClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use RuntimeException;

class AccessTokenService extends EventosClient
{
    public function __construct()
    {
        parent::__construct('web_api');
    }

    /**
     * Validate an access-token by calling web_api/v2/personal/{portal}/{event}.
     *
     * Returns the user data array on success (HTTP 200).
     *
     * @throws UnauthorizedException  token invalid/expired → caller returns 401
     * @throws RuntimeException       eventoa API down/timeout → caller returns 503
     */
    public function validate(string $accessToken): array
    {
        $portal = $this->_getPortal();
        $event  = $this->_getEvent();

        $s       = app(MatchingCsvDownloadSetting::class);
        $timeout = (int) ($s?->api_timeout_seconds ?? 3);

        $client = $this->createApiClient();
        $client->setMethod('GET');
        $client->setEndpoint("/web_api/v2/personal/{$portal}/{$event}");
        $client->setHeaders(['access-token' => $accessToken]);
        $client->setTimeout($timeout);

        try {
            $response = $client->send();

            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException('Unexpected status: '.$response->getStatusCode());
            }

            return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        } catch (ClientException $e) {
            $status  = $e->getResponse()->getStatusCode();
            $body    = json_decode($e->getResponse()->getBody()->getContents(), true);
            $message = $body['error_message'] ?? $body['message'] ?? 'Unauthorized';

            if ($status === 401) {
                throw new UnauthorizedException($message);
            }
            throw new RuntimeException($message, $status, $e);

        } catch (ConnectException $e) {
            throw new RuntimeException('Eventos API connection failed.', 503, $e);

        } catch (ServerException $e) {
            $status  = $e->getResponse()->getStatusCode();
            $body    = json_decode((string) $e->getResponse()->getBody(), true);
            $message = $body['error']['items'][0]['message']
                ?? $body['error_message']
                ?? $body['message']
                ?? 'Eventos API server error.';
            throw new RuntimeException($message, $status, $e);
        }
    }
}
