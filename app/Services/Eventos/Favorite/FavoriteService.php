<?php

namespace App\Services\Eventos\Favorite;

use App\Services\Eventos\EventosClient;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\ClientExceptionInterface;
use Throwable;

final class FavoriteService extends EventosClient
{

    public function __construct()
    {
        parent::__construct('public');
    }

    /**
     * @throws GuzzleException
     * @throws ClientExceptionInterface
     */
    public function getFavoriteListByUserUuid(string $uuid): array
    {
        try {
            $client = $this->createApiClient();
            $client->setMethod('GET');
            $client->setEndpoint("/api/v1/favorite/list/uuid/$uuid");

            $response = $client->send();

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }
            throw new \Exception('Failed to get favorites by UUID. Status: '.$response->getStatusCode());
        } catch (GuzzleException|ClientExceptionInterface|Exception $e) {
            throw new \Exception('Error fetching favorite list: '.$e->getMessage());
        }
    }

    /**
     * @throws GuzzleException
     * @throws ClientExceptionInterface|Throwable
     */
    public function likeBooth(array $params): array
    {
        $user = auth()->user();
        $uuid = $user->uuid;
        try {
            $client = $this->createApiClient();
            $client->setMethod('POST');
            $client->setEndpoint("/api/v1/favorite/booth/".$params["booth_id"]."/uuid/".$uuid);
            $body = ['favorite' => (bool)$params['favorite']];
            $client->setBody($body);
            $response = $client->send();

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }
            throw new \Exception('Failed to get favorites by UUID. Status: '.$response->getStatusCode());
        } catch (GuzzleException|ClientExceptionInterface|Exception $e) {
            throw new \Exception('Error post favorite: '.$e->getMessage());
        }
    }

    /**
     * @throws GuzzleException
     * @throws ClientExceptionInterface|Throwable
     */
    public function favoriteList(): array
    {
        $user = auth()->user();
        $uuid = $user->uuid;
        try {
            $client = $this->createApiClient();
            $client->setMethod('GET');
            $client->setEndpoint("/api/v1/favorite/list/uuid/".$uuid);
            $response = $client->send();

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }
            throw new \Exception('Failed to get favorites by UUID. Status: '.$response->getStatusCode());
        } catch (GuzzleException|ClientExceptionInterface|Exception $e) {
            throw new \Exception('Error post favorite: '.$e->getMessage());
        }
    }
}
