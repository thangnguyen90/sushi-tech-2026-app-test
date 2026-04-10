<?php

namespace App\Services\Eventos\User;

use App\Services\Eventos\EventosClient;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\EachPromise;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class UserService extends EventosClient
{
    private const int MAX_USER_LIST_PAGES = 500;

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
        $cacheKey = 'user_by_uuid_'.$uuid;
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

                throw $e;
            }

            throw new RuntimeException('Failed to get user by UUID. Status: ');
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
                $client->setEndpoint('/api/v1/user/profiles');
                $response = $client->send();

                if ($response->getStatusCode() === 200) {
                    return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                }
                throw new Exception('Failed to get profiles. Status: ');
            } catch (Exception $e) {
                if ($e instanceof ClientException || $e instanceof ServerException) {
                    $errorMessage = json_decode($e->getResponse()->getBody(), true);
                    throw new Exception($errorMessage['error_message'], 404, $e);
                }
                throw $e;
            }
        });
    }

    /**
     * @throws Exception | GuzzleException | Throwable
     */
    public function getUsersList(int $cacheDuration = 60): array
    {
        $cacheKey = 'user_list';
        $fetchUsers = function (): array {
            $allUsers = [];
            $firstPayload = null;

            $this->forEachUserListPage(function (array $payload, int $page) use (&$allUsers, &$firstPayload): void {
                $firstPayload ??= $payload;

                foreach ($this->extractUsersListItems($payload) as $user) {
                    $allUsers[] = $user;
                }
            });

            return $this->mergeUsersListItemsIntoPayload($firstPayload ?? [], $allUsers);
        };

        if ($cacheDuration === 0) {
            return $fetchUsers();
        }

        return Cache::remember($cacheKey, $cacheDuration, $fetchUsers);
    }

    /**
     * @param  callable(array, int): void  $pageProcessor
     *
     * @throws Exception | GuzzleException | Throwable
     */
    public function forEachUserListPage(callable $pageProcessor): void
    {
        $page = 1;
        $pagesFetched = 0;

        while ($pagesFetched < self::MAX_USER_LIST_PAGES) {
            $pagesFetched++;
            $payload = $this->requestUsersListPage($page);
            $pageProcessor($payload, $page);

            $nextPage = $this->resolveNextUsersListPage($payload, $page);
            if ($nextPage === null || $nextPage <= $page) {
                return;
            }

            $page = $nextPage;
        }

        throw new RuntimeException(sprintf(
            'Reached the maximum allowed page count while fetching Eventos user list (%d pages).',
            self::MAX_USER_LIST_PAGES
        ));
    }

    /**
     * Fetch all user list pages, requesting up to $concurrency pages at a time.
     * Passes $perPage as the per_page query parameter when > 0.
     *
     * @param  callable(array, int): void  $pageProcessor
     *
     * @throws Exception | GuzzleException | Throwable
     */
    public function forEachUserListPageParallel(callable $pageProcessor, int $concurrency = 1, int $perPage = 100): void
    {
        // Fetch page 1 synchronously to discover pagination metadata
        $firstPayload = $this->requestUsersListPage(1, $perPage);
        $pageProcessor($firstPayload, 1);

        $lastPageNumber = $this->resolveLastUsersListPageNumber($firstPayload);

        if ($lastPageNumber === null) {
            // Cannot determine total pages upfront; fall back to sequential
            $page = $this->resolveNextUsersListPage($firstPayload, 1);
            $pagesFetched = 1;

            while ($page !== null && $pagesFetched < self::MAX_USER_LIST_PAGES) {
                $pagesFetched++;
                $payload = $this->requestUsersListPage($page, $perPage);
                $pageProcessor($payload, $page);
                $nextPage = $this->resolveNextUsersListPage($payload, $page);
                if ($nextPage === null || $nextPage <= $page) {
                    return;
                }
                $page = $nextPage;
            }

            if ($pagesFetched >= self::MAX_USER_LIST_PAGES) {
                throw new RuntimeException(sprintf(
                    'Reached the maximum allowed page count while fetching Eventos user list (%d pages).',
                    self::MAX_USER_LIST_PAGES
                ));
            }

            return;
        }

        if ($lastPageNumber <= 1) {
            return;
        }

        $remainingPages = range(2, min($lastPageNumber, self::MAX_USER_LIST_PAGES));

        $requestFactory = function () use ($remainingPages, $perPage): \Generator {
            foreach ($remainingPages as $page) {
                $client = $this->createApiClient();
                $client->setMethod('GET');
                $client->setEndpoint('/api/v1/user/list');
                $params = ['page' => $page];
                if ($perPage > 0) {
                    $params['per_page'] = $perPage;
                }
                $client->setQueryParams($params);
                yield $page => $client->sendAsync();
            }
        };

        $firstRejection = null;
        $each = new EachPromise($requestFactory(), [
            'concurrency' => $concurrency,
            'fulfilled' => function ($response, $page) use ($pageProcessor): void {
                $payload = json_decode($response->getBody()->getContents(), true);
                $pageProcessor($payload, $page);
            },
            'rejected' => function ($reason, $page) use (&$firstRejection): void {
                $firstRejection ??= new RuntimeException(
                    sprintf('Failed to fetch Eventos user list page %d.', $page),
                    0,
                    $reason instanceof \Throwable ? $reason : null
                );
            },
        ]);
        $each->promise()->wait();

        if ($firstRejection !== null) {
            throw $firstRejection;
        }
    }

    private function resolveLastUsersListPageNumber(array $payload): ?int
    {
        $perPage = $this->extractIntFromPayload($payload, [
            'per_page',
            'meta.per_page',
            'pagination.per_page',
        ]);
        $total = $this->extractIntFromPayload($payload, [
            'total',
            'meta.total',
            'pagination.total',
        ]);

        if ($total !== null && $perPage !== null && $perPage > 0) {
            return (int) ceil($total / $perPage);
        }

        return $this->extractIntFromPayload($payload, [
            'last_page',
            'meta.last_page',
            'pagination.last_page',
        ]);
    }

    /**
     * @throws Exception | GuzzleException | Throwable
     */
    protected function requestUsersListPage(int $page, int $perPage = 0): array
    {
        $client = $this->createApiClient();
        $client->setMethod('GET');
        $client->setEndpoint('/api/v1/user/list');
        $params = ['page' => $page];
        if ($perPage > 0) {
            $params['per_page'] = $perPage;
        }
        $client->setQueryParams($params);

        try {
            $response = $client->send();

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }
        } catch (Exception $e) {
            if ($e instanceof ClientException || $e instanceof ServerException) {
                $errorMessage = json_decode($e->getResponse()->getBody(), true, 512, JSON_THROW_ON_ERROR);
                throw new Exception($errorMessage['error_message'], 404, $e);
            }

            throw $e;
        }

        throw new RuntimeException('Failed to get user list. Status: ');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractUsersListItems(array $payload): array
    {
        $candidates = [
            data_get($payload, 'data'),
            data_get($payload, 'users'),
            data_get($payload, 'list'),
            data_get($payload, 'result'),
            $payload,
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || ! array_is_list($candidate)) {
                continue;
            }

            $rows = array_values(array_filter($candidate, static fn (mixed $item): bool => is_array($item)));
            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $allUsers
     * @return array<string, mixed>|array<int, mixed>
     */
    private function mergeUsersListItemsIntoPayload(array $payload, array $allUsers): array
    {
        if (array_is_list($payload)) {
            return $allUsers;
        }

        foreach (['data', 'users', 'list', 'result'] as $key) {
            if (array_key_exists($key, $payload) && is_array($payload[$key])) {
                $payload[$key] = $allUsers;

                return $payload;
            }
        }

        $payload['data'] = $allUsers;

        return $payload;
    }

    private function resolveNextUsersListPage(array $payload, int $currentPage): ?int
    {
        $page = $this->extractIntFromPayload($payload, [
            'page',
            'meta.page',
            'pagination.page',
            'current_page',
            'meta.current_page',
            'pagination.current_page',
        ]) ?? $currentPage;
        $perPage = $this->extractIntFromPayload($payload, [
            'per_page',
            'meta.per_page',
            'pagination.per_page',
        ]);
        $total = $this->extractIntFromPayload($payload, [
            'total',
            'meta.total',
            'pagination.total',
        ]);

        if ($total !== null && $perPage !== null && $perPage > 0) {
            $lastPage = (int) ceil($total / $perPage);

            return $page < $lastPage ? $page + 1 : null;
        }

        $lastPage = $this->extractIntFromPayload($payload, [
            'last_page',
            'meta.last_page',
            'pagination.last_page',
        ]);

        if ($lastPage !== null) {
            return $page < $lastPage
                ? $page + 1
                : null;
        }

        $nextPageUrl = $this->extractStringFromPayload($payload, [
            'next_page_url',
            'meta.next_page_url',
            'pagination.next_page_url',
            'links.next',
        ]);

        if ($nextPageUrl === null) {
            return null;
        }

        $queryString = parse_url($nextPageUrl, PHP_URL_QUERY);
        if (! is_string($queryString) || $queryString === '') {
            return $page + 1;
        }

        parse_str($queryString, $query);
        $nextPage = $query['page'] ?? null;

        if (is_numeric($nextPage)) {
            return (int) $nextPage;
        }

        return $page + 1;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function extractIntFromPayload(array $payload, array $paths): ?int
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (is_int($value)) {
                return $value;
            }

            if (! is_scalar($value)) {
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized === '' || preg_match('/^-?\d+$/', $normalized) !== 1) {
                continue;
            }

            return (int) $normalized;
        }

        return null;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function extractStringFromPayload(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (! is_scalar($value)) {
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized === '') {
                continue;
            }

            return $normalized;
        }

        return null;
    }

    /**
     * @throws Throwable
     * @throws GuzzleException
     */
    public function getTickets(string $uuid, ?int $moduleId = null): array
    {
        $client = $this->createApiClient();
        $client->setMethod('GET');
        $module = $moduleId ?? $this->moduleTicketId;
        $client->setEndpoint("api/v1/ticket/normal/order/$module/uuid/$uuid");

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
            $client->setEndpoint('/api/v1/user/profiles');

            $response = $client->send();

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            }
            throw new \RuntimeException('Failed to get user share profile. Status: 500', 500);
        });
    }
}
