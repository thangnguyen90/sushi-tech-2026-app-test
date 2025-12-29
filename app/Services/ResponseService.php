<?php

namespace App\Services;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use JsonSerializable;

final class ResponseService
{
    public const int DEFAULT_SUCCESS_CODE = 200;
    public const int DEFAULT_ERROR_CODE   = 500;
    public array $headers = [];
    public function __construct(
        private readonly ResponseFactory $response
    ) {

    }

    public function success(
        mixed $data = null,
        int|string $code = self::DEFAULT_SUCCESS_CODE,
        string $message = 'OK',
        int $status = 200,
        array $headers = [],
        ?int $cacheTtlSeconds = null,
        bool $public = true
    ): JsonResponse {
        $payload = $this->makePayload($code, $message, $data);
        $this->headers = array_merge($this->headers, $headers);

        return $this->respond(
            payload: $payload,
            status: $status,
            cacheTtlSeconds: $cacheTtlSeconds,
            public: $public
        );
    }

    public function error(
        string $message = 'Error',
        int|string $code = self::DEFAULT_ERROR_CODE,
        mixed $data = null,
        int $status = 400,
        array $headers = []
    ): JsonResponse {
        $payload = $this->makePayload( $code, $message, $data);

        $this->headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ];

        return $this->respond($payload, $status, cacheTtlSeconds: null);
    }

    public function respond(
        array $payload,
        int $status = 200,
        ?int $cacheTtlSeconds = null,
        bool $public = true
    ): JsonResponse {
        if ($cacheTtlSeconds === null) {
            $cacheTtlSeconds = config('app.cloudfront_cache_tls', 60);
        }
        if ($cacheTtlSeconds === 0  || config('app.cloudfront_cache_tls') == 0) {
            $this->headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
            $this->headers['Pragma']        = 'no-cache';
        }

        $resp = $this->response->json($payload, $status, $this->headers);

        if ($cacheTtlSeconds !== null) {
            $public ? $resp->setPublic() : $resp->setPrivate();
            $resp->setMaxAge($cacheTtlSeconds);
            $resp->headers->addCacheControlDirective('must-revalidate', true);
        }

        return $resp;
    }

    /**
     * Build unified API payload.
     */
    private function makePayload(
        int|string $code,
        string $message,
        mixed $data = null
    ): array {
        return [
            'code'    => $code,
            'message' => $message,
            'result'    => $this->normalizeData($data),
        ];
    }

    private function normalizeData(mixed $data): mixed
    {
        if ($data instanceof Arrayable) {
            return $data->toArray();
        }
        return $data;
    }
}
