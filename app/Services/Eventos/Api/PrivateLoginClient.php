<?php

namespace App\Services\Eventos\Api;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;

final class PrivateLoginClient extends ApiResource
{
    /** @var string The cache key for the admin cookies. */
    private const string CACHE_KEY_ADMIN_COOKIES = 'admin-cookies';

    /**
     * PrivateLoginClient constructor.
     *
     * @param string $baseUrl The base URL for the private API.
     * @param string $username The admin username (email).
     * @param string $password The admin password.
     * @throws Exception
     */
    public function __construct(string $baseUrl, string $username, string $password)
    {
        // Call parent constructor first
        parent::__construct($baseUrl);

        $this->setMethod('POST');
        $this->setEndpoint('/console/api/login');
        $this->setHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);
        $this->setBody([
            'mail_address' => $username,
            'password' => $password,
        ]);
    }

    /**
     * Executes the login request and returns the cookies.
     *
     * @return array The authenticated session cookies.
     *
     * @throws GuzzleException|Exception|\Throwable
     */
    public function __invoke(): array
    {
        // The try-catch block is removed because the parent::send() already handles
        // logging and re-throwing exceptions in a centralized way.
        $response = parent::send();

        // Check status code instead of reason phrase for reliability.
        if ($response->getStatusCode() === 200) {
            $cookies = $this->getCookies();

            // Find the minimum expiration time from the cookies to set cache duration.
            $cacheDuration = $this->_getCacheDuration($cookies);
            if ($cacheDuration > 0) {
                Cache::put(self::CACHE_KEY_ADMIN_COOKIES, $cookies, $cacheDuration);
            }

            return $cookies;
        }

        throw new Exception('Private login failed. Status: '.$response->getStatusCode());
    }

    /**
     * Calculates the minimum cache duration from a list of cookies.
     */
    private function _getCacheDuration(array $cookies): int
    {
        $maxAgeValues = [];
        foreach ($cookies as $cookie) {
            // Consider only cookies that have a Max-Age set
            if (isset($cookie['Max-Age']) && $cookie['Max-Age'] > 0) {
                $maxAgeValues[] = $cookie['Max-Age'] ; // Subtract random seconds to avoid simultaneous expiry
            }
        }

        return ! empty($maxAgeValues) ? min($maxAgeValues) - random_int(20, 50)  : 0;
    }
}
