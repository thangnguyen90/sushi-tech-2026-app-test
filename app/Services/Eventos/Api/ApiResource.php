<?php

namespace App\Services\Eventos\Api;

use App\Services\Slack\SlackWebhookAdmin;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Throwable;

abstract class ApiResource
{
    /** ---- Runtime configs (extractable ra config/env) ---- */
    private const MAX_RETRIES = 3;      // max retry attempts

    private const MAX_RATE_LIMIT_DELAY_SECONDS = 8;

    private string $baseUrl;

    private string $endpoint;

    private string $method;

    public array $headers = [];

    private mixed $body = null;

    private array $query = [];

    private string $rawQuery = '';

    private array $cookies = [];

    /** Callable to refresh authentication, if needed */
    public $authRefresher = null;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function setEndpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function setHeaders(array|string $headers, ?string $value = null): self
    {
        if (is_array($headers)) {
            $this->headers = array_merge($this->headers, $headers);
        } elseif (is_string($headers) && $value !== null) {
            $this->headers[$headers] = $value;
        } else {
            throw new Exception('Headers must be an array or a string with a value.');
        }

        return $this;
    }

    public function setBody(mixed $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function setQueryParams(array $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function setRawQuery(string $rawQuery): self
    {
        $this->rawQuery = ltrim($rawQuery, '&?');

        return $this;
    }

    public function setCookies(array $cookies): self
    {
        $this->cookies = $cookies;

        return $this;
    }

    public function setAuthRefresher(callable $refresher): self
    {
        $this->authRefresher = $refresher;

        return $this;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    private function buildRequestOptions(): array
    {
        $options = ['headers' => $this->headers];

        if (isset($this->body)) {
            if (is_array($this->body) && array_key_exists('multipart', $this->body)) {
                $options['multipart'] = $this->body['multipart'];
            } else {
                $options['json'] = $this->body;
            }
        }

        return $options;
    }

    private function buildRequestEndpoint(): string
    {
        $endpoint = $this->endpoint;

        $hasQuery = ! empty($this->query);
        $hasRaw = $this->rawQuery !== '';

        if ($hasQuery) {
            $endpoint .= '?'.http_build_query($this->query);
            if ($hasRaw) {
                $endpoint .= '&'.$this->rawQuery;
            }
        } elseif ($hasRaw) {
            $endpoint .= '?'.$this->rawQuery;
        }

        return $endpoint;
    }

    /**
     * @throws GuzzleException
     * @throws Exception|Throwable
     */
    public function send(): ResponseInterface
    {
        if (! isset($this->method)) {
            throw new Exception('Request method is not defined.');
        }
        $retries = 0;

        call:
        $cookieJar = new CookieJar;
        foreach ($this->cookies as $cookie) {
            $cookieJar->setCookie(new SetCookie($cookie));
        }

        $client = new Client(['cookies' => $cookieJar, 'base_uri' => $this->baseUrl]);
        $options = ['headers' => $this->headers];
        $requestEndpoint = $this->endpoint;

        if (! empty($this->query)) {
            $requestEndpoint .= '?'.http_build_query($this->query);
            if (! empty($this->rawQuery)) {
                $requestEndpoint .= '&'.$this->rawQuery;
            }
        } elseif (! empty($this->rawQuery)) {
            $requestEndpoint .= '?'.$this->rawQuery;
        }

        if (isset($this->body)) {
            if (isset($this->body['multipart'])) {
                $options['multipart'] = $this->body['multipart'];
            } else {
                $options['json'] = $this->body;
            }
        }
        try {
            $retries++;
            $response = $client->request($this->method, $requestEndpoint, $options);
        } catch (Throwable $e) {
            if ($e instanceof ClientException) {
                $response = $e->getResponse();
                $status = $response->getStatusCode();

                if ($status === 401) {
                    $refreshed = false;
                    if (is_callable($this->authRefresher)) {

                        try {
                            $refreshed = call_user_func($this->authRefresher, $this);
                            Log::info('Auth refresher executed', [
                                'refreshed' => (bool) $refreshed,
                                'attempt' => $retries + 1,
                                'header' => $this->headers,
                                'url' => $this->baseUrl.$this->endpoint,
                            ]);
                        } catch (Throwable $e) {
                            Log::error('Auth refresher thrown exception', [
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                    if ($retries <= self::MAX_RETRIES && $refreshed) {
                        goto call;
                    }
                }

                if ($status === 429 && $retries < self::MAX_RETRIES) {
                    $retryDelaySeconds = $this->resolveRateLimitDelaySeconds($response, $retries);

                    Log::warning('API request rate limited. Retrying request.', [
                        'attempt' => $retries + 1,
                        'delay_seconds' => $retryDelaySeconds,
                        'url' => $this->baseUrl.$requestEndpoint,
                    ]);

                    usleep($retryDelaySeconds * 1_000_000);

                    goto call;
                }
            }
            $this->_handleRequestException($e);
        }

        $this->setCookies($cookieJar->toArray());

        return $response;
    }

    /**
     * @throws GuzzleException
     * @throws Exception|Throwable
     */
    public function sendAsync(): PromiseInterface
    {
        if (! isset($this->method)) {
            throw new Exception('Request method is not defined.');
        }

        $cookieJar = new CookieJar;
        foreach ($this->cookies as $cookie) {
            $cookieJar->setCookie(new SetCookie($cookie));
        }

        $client = new Client(['cookies' => $cookieJar, 'base_uri' => $this->baseUrl]);
        $options = $this->buildRequestOptions();
        $uri = $this->buildRequestEndpoint();

        try {
            $promise = $client->requestAsync($this->method, $uri, $options);
        } catch (Throwable $e) {
            $this->_handleRequestException($e);
        }

        $this->setCookies($cookieJar->toArray());

        return $promise;
    }

    private function resolveRateLimitDelaySeconds(ResponseInterface $response, int $attempt): int
    {
        $retryAfterHeader = $response->getHeaderLine('Retry-After');

        if (is_numeric($retryAfterHeader)) {
            $retryAfterSeconds = (int) $retryAfterHeader;

            if ($retryAfterSeconds > 0) {
                return min($retryAfterSeconds, self::MAX_RATE_LIMIT_DELAY_SECONDS);
            }
        }

        return min(2 ** max($attempt - 1, 0), self::MAX_RATE_LIMIT_DELAY_SECONDS);
    }

    /**
     * @throws Throwable
     */
    private function _handleRequestException(Throwable $e): void
    {
        $errorType = get_class($e);
        $code = $e->getCode();
        $message = $e->getMessage();
        Log::error($e);
        $logContext = [
            'type' => $errorType,
            'code' => $code,
            'url' => $this->baseUrl.$this->endpoint,
            'error' => $message,
        ];

        $slackService = new SlackWebhookAdmin;
        $slackService->send(
            header: "API Request Failed: {$errorType}",
            message: $message,
            file: $e->getFile(),
            line: $e->getLine()
        );

        if ($e instanceof ClientException || $e instanceof ServerException) {
            $responseBody = $e->getResponse()?->getBody()?->getContents();
            $logContext['response_body'] = json_decode($responseBody ?? '', true) ?? $responseBody;
        }

        Log::error('API Request General Error', $logContext);
        throw $e;
    }
}
