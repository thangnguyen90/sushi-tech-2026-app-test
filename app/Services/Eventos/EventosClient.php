<?php

namespace App\Services\Eventos;

use App\Services\Eventos\Api\ApiResource;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Client\ClientExceptionInterface;

class EventosClient extends ConfigureAbstract
{
    /**
     * EventosClient constructor.
     *
     * @param string $status 'Public' or 'Private'.
     */
    public function __construct(string $status = 'Private')
    {
        parent::__construct($status);
    }

    /**
     * Gets all public-related configuration info.
     *
     * @throws Exception|GuzzleException
     */
    public function getPublicInfo(): array
    {
        // call protected method directly for clarity
        return $this->_getInfoPublic();
    }

    /**
     * Gets all private-related configuration info.
     *
     * @throws ClientExceptionInterface|GuzzleException
     */
    public function getPrivateInfo(): array
    {
        // call protected method directly for clarity
        return $this->_getInfoPrivate();
    }


    /**
     * @throws GuzzleException|Exception
     */
    public function createApiClient(): ApiResource
    {
        $client = new class ( $this->getBaseUrl() ) extends ApiResource { };
        $client->setHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);
        match ($this->status) {
            'Private' => $client->setCookies($this->getCookies()),
            'web_api' => $client,
            default => $client->setHeaders([
                'X-Api-Key' => $this->_getPublicSecret(),
                'token' => $this->getPublicToken(),
            ]),
        };
        $client->setAuthRefresher(function (ApiResource $client): bool {
            return $this->refreshAuth($client);
        });

        return $client;
    }

    /**
     * Gets the base URL for API calls.
     */
    public function getBaseUrl(): string
    {
        return match ($this->status) {
            'Private' => $this->_getBaseUrl(),
            'web_api' => $this->_getBaseUrlWebApi(),
            default => $this->_getPublicUrl(),
        };
    }

    /**
     * Gets the authenticated admin cookies.
     *
     * @throws ClientExceptionInterface|GuzzleException
     */
    public function getCookies(): array
    {
        return $this->_getCookies();
    }

    /**
     * Gets the public API token.
     *
     * @throws GuzzleException
     */
    public function getPublicToken(): string
    {
        return $this->_getPublicToken();
    }

    private function refreshAuth(ApiResource $resource): bool
    {
        try {
            switch ($this->status) {
                case 'Private':
                    $this->cookie = null;
                    $newCookies = $this->_getCookies(true);
                    if (!empty($newCookies)) {
                        $resource->setCookies($newCookies);
                        return true;
                    }
                    return false;

                case 'web_api':
                    // if web not have token refresh mechanism
                    return false;

                default:
                    // renew token for public API
                    $this->publicToken = null;
                    $newToken = $this->_getPublicToken(true);
                    if (!empty($newToken)) {
                        $resource->setHeaders('token', $newToken);
                        return true;
                    }
                    return false;
            }
        } catch (\Throwable $e) {
            Log::info('Error refreshing auth', ['error' => $e->getMessage()]);
            Log::error($e);
            return false;
        }
    }
}
