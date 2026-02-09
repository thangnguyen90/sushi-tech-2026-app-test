<?php

namespace App\Services\Eventos\User;

use App\Services\Eventos\EventosClient;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class UserService extends EventosClient
{
    public function __construct()
    {
        $status = 'public';
        parent::__construct($status);
    }

    /**
     * Get users bvy uuid.
     *
     * @throws Exception | GuzzleException | Throwable
     */
    public function getUsersByUuid(string $uuid, int $cacheDuration = 60): array
    {
        $cacheKey = 'user_by_uuid_' . $uuid;
        // $cacheDuration = 60; // 1 minute
        $fetchUser = function () use ($uuid) {
            $client = $this->createApiClient();
            $client->setMethod('GET');
            $client->setEndpoint("/api/v1/user/uuid/$uuid");

            try {
                $response = $client->send();

                if ($response->getStatusCode() === 200) {
                    return json_decode($response->getBody()->getContents(), true);
                }
            } catch (Exception $e) {
                if ($e instanceof ClientException || $e instanceof ServerException) {
                    // Log the error or handle it as needed
                    $errorMessage = json_decode($e->getResponse()->getBody(), true);
                    throw new Exception($errorMessage['error_message'], 404, $e);
                }
            }

            throw new \RuntimeException('Failed to get user by UUID. Status: ');
        };

        if ($cacheDuration === 0) {
            return $fetchUser();
        }

        return Cache::remember($cacheKey, $cacheDuration, $fetchUser);
    }

    public function getProfiles(): array
    {
        $cacheKey = 'getProfiles';
        $cacheDuration = 60 * 2; // 2 minutes
        return Cache::remember($cacheKey, $cacheDuration, function () {
            try {

                $client = $this->createApiClient();
                $client->setMethod('GET');
                $client->setEndpoint("/api/v1/user/profiles");
                $response = $client->send();

                if ($response->getStatusCode() === 200) {
                    return json_decode($response->getBody()->getContents(), true);
                }
                throw new Exception('Failed to get profiles. Status: ');
            } catch (Exception $e) {
                if($e instanceof ClientException || $e instanceof ServerException) {
                    $errorMessage = json_decode($e->getResponse()->getBody(), true);
                    throw new Exception($errorMessage['error_message'], 404, $e);
                }
                throw $e;
            }
        });
    }

    /**
     * @throws Throwable
     * @throws GuzzleException
     */
    public function getTickets(string $uuid, int $moduleId = null): array
    {
        $client = $this->createApiClient();
        $client->setMethod('GET');
        $module = $moduleId??$this->moduleTicketId;
        $client->setEndpoint("api/v1/ticket/normal/order/$module/uuid/$uuid");

        try {
            $response = $client->send();

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }
        } catch (Exception $e) {
            if($e instanceof ClientException || $e instanceof ServerException) {
                // Log the error or handle it as needed
                $errorMessage = json_decode($e->getResponse()->getBody(), true);
                throw new Exception($errorMessage['error_message'], 404, $e);
            }
            throw $e;
        }

        throw new \RuntimeException('Failed to get profiles.');
    }

    public function getUserShareProfile()
    {
        $cacheKey = 'user_share_profile';
        $cacheDuration = 60 * 60 * 2;

        return Cache::remember($cacheKey, $cacheDuration, function () {
            $client = $this->createApiClient();
            $client->setMethod('GET');
            $client->setEndpoint("/api/v1/user/profiles");

            $response = $client->send();

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            }
            throw new \RuntimeException('Failed to get user share profile. Status: 500', 500);
        });
    }
}
