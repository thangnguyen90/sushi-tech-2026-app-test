<?php

namespace App\Services\Eventos\Api;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;

final class PublicLoginClient extends ApiResource
{
    /** @var string The cache key for the public access token. */
    private const CACHE_KEY_PUBLIC_TOKEN = 'public_token';

    /** @var int The buffer time in seconds before the token expires. */
    private const TOKEN_EXPIRATION_BUFFER_SECONDS = 60;

    /** @var string The timezone used for expiration calculation. */
    private const TIMEZONE = 'Asia/Tokyo';

    /**
     * PublicLoginClient constructor.
     *
     * @param  string  $publicUrl  The base URL for the public API.
     * @param  string  $secret  The API secret key (x-api-key).
     */
    public function __construct(string $publicUrl, string $secret)
    {
        parent::__construct($publicUrl);

        $this->setMethod('GET');
        $this->setEndpoint('/api/v1/token');
        $this->setHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'x-api-key' => $secret,
        ]);
    }

    /**
     * Invoke the client to get an access token.
     *
     * @return string The access token.
     *
     * @throws GuzzleException
     * @throws Exception
     */
    public function __invoke(): string
    {
        $response = parent::send();

        if ($response->getStatusCode() === 200) {
            $tokenData = json_decode($response->getBody()->getContents(), true);
            $accessToken = $tokenData['access_token'];

            $expiresAt = Carbon::parse($tokenData['expired_at'], self::TIMEZONE);
            $cacheTime = Carbon::now(self::TIMEZONE)->diffInSeconds($expiresAt) - self::TOKEN_EXPIRATION_BUFFER_SECONDS;

            if ($cacheTime > 0) {
                Cache::put(self::CACHE_KEY_PUBLIC_TOKEN, $accessToken, $cacheTime);
            }

            return $accessToken;
        }

        // Let the caller handle the exception if the request fails
        throw new Exception('Failed to retrieve public token. Status: '.$response->getStatusCode());
    }
}
