<?php

namespace App\Http\Middleware;

use App\Services\SlackWebhookService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class LogApiRequest
{
    private const int MAX_BODY_LENGTH = 20000; // max log body size in bytes
    private const int MAX_SLACK_RESPONSE_BODY = 3000;

    public function __construct(
        private readonly SlackWebhookService $slack,
    ) {}

    /**
     * @throws JsonException
     */
    public function handle(Request $request, Closure $next)
    {
        $requestId = $this->getOrCreateRequestId($request);

        // Prepare data once (used for log + slack)
        $headers = $this->sanitizeHeaders($request->headers->all());
        $body    = $this->extractBody($request);

        // Attach context early
        Log::withContext([
            'request_id' => $requestId,
            'method'     => $request->method(),
            'path'       => $request->path(),
        ]);

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->sendSlackOnException($request, $requestId, $headers, $body, $e);
            throw $e;
        }

        // set a response header for tracing
        if (method_exists($response, 'headers')) {
            $response->headers->set('X-Request-Id', $requestId);
        }

        $statusCode = method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : 0;

        // Nếu bạn chỉ muốn notify khi 5xx
        if ($statusCode >= 300) {
            $this->sendSlackOnHttpError($request, $requestId, $headers, $body, $response, $statusCode);
        }

        // Nếu bạn muốn thêm 4xx (tuỳ chọn)
        // if ($statusCode >= 400) { ... }

        // Log (giữ logic cũ, nhưng đừng chặn production nếu mục tiêu là alert production)
        // Nếu bạn chỉ muốn log debug ở local, giữ if() cho Log::info, còn Slack vẫn gửi theo status/exception.
        if (! (app()->environment('product', 'production', 'prod') || ! config('app.debug'))) {
            if (! in_array($statusCode, [200, 201], true)) {
                Log::info('request', [
                    'ip'         => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'headers'    => $headers,
                    'query'      => $this->sanitizeArray($request->query()),
                    'body'       => $body,
                ]);

                Log::info('api.response', [
                    'status' => $statusCode,
                ]);
            }
        }

        return $response;
    }

    private function sendSlackOnException(Request $request, string $requestId, array $headers, mixed $body, Throwable $e): void
    {
        // Header ngắn gọn để Slack dễ scan
        $header = sprintf(
            '[API][EXCEPTION][%s] %s %s',
            strtoupper((string) app()->environment()),
            $request->method(),
            $request->path()
        );

        $payload = [
            'request_id' => $requestId,
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
            'query'      => $this->sanitizeArray($request->query()),
            'headers'    => $headers,
            'body'       => $body,
            'error'      => [
                'type'    => get_class($e),
                'message' => $e->getMessage(),
            ],
        ];

        $this->slack->send(
            $header,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),
            $e->getFile(),
            $e->getLine()
        );
    }

    private function sendSlackOnHttpError(Request $request, string $requestId, array $headers, mixed $body, $response, int $statusCode): void
    {
        $header = sprintf(
            '[API][HTTP_%d][%s] %s %s',
            $statusCode,
            strtoupper((string) app()->environment()),
            $request->method(),
            $request->path()
        );

        $responseBody = null;
        try {
            $content = method_exists($response, 'getContent') ? $response->getContent() : null;
            if (is_string($content) && $content !== '') {
                $responseBody =json_decode($content,true);
            }
        } catch (Throwable) {
            $responseBody = null;
        }

        $payload = [
            'request_id'    => $requestId,
            'ip'            => $request->ip(),
            'user_agent'    => $request->userAgent(),
            'query'         => $this->sanitizeArray($request->query()),
            'headers'       => $headers,
            'body'          => $body,
            'response'      => [
                'status' => $statusCode,
                'body'   => $responseBody,
            ],
        ];

        $this->slack->send(
            $header,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT )
        );
    }

    private function truncateString(string $text, int $max): string
    {
        if ($max <= 0) return '';
        if (mb_strlen($text) <= $max) return $text;
        return mb_substr($text, 0, $max - 1) . '…';
    }

    private function getOrCreateRequestId(Request $request): string
    {
        $incoming = $request->headers->get('X-Request-Id');
        return $incoming && is_string($incoming) ? $incoming : (string) Str::uuid();
    }

    /**
     * @throws JsonException
     */
    private function extractBody(Request $request)
    {
        if ($request->isJson()) {
            $data = $request->json()?->all();
            return $this->limitSize($this->sanitizeArray($data ?? []));
        }

        $data = $request->all();

        if (! empty($request->allFiles())) {
            $data['_files'] = $this->filesMetadata($request->allFiles());
        }

        return $this->limitSize($this->sanitizeArray($data));
    }

    private function filesMetadata(array $files): array
    {
        $out = [];

        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $out[$key] = $this->filesMetadata($file);
                continue;
            }

            $out[$key] = [
                'original_name' => method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : null,
                'mime'          => method_exists($file, 'getClientMimeType') ? $file->getClientMimeType() : null,
                'size'          => method_exists($file, 'getSize') ? $file->getSize() : null,
            ];
        }

        return $out;
    }

    private function sanitizeHeaders(array $headers): array
    {
        $flat = array_map(static function ($v) {
            return is_array($v) ? implode(', ', $v) : (string) $v;
        }, $headers);

        return $this->sanitizeArray($flat);
    }

    private function sanitizeArray(array $data): array
    {
        $masked = [];
        foreach ($data as $key => $value) {
            $keyLower = strtolower((string) $key);

            if ($this->shouldMaskKey($keyLower)) {
                $masked[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $masked[$key] = $this->sanitizeArray($value);
                continue;
            }

            if (is_string($value) && mb_strlen($value) > 1500) {
                $masked[$key] = mb_substr($value, 0, 1500) . '...';
                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }

    /**
     * @throws JsonException
     */
    private function limitSize($data)
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            return $data;
        }

        if (strlen($json) <= self::MAX_BODY_LENGTH) {
            return $data;
        }

        $truncated = substr($json, 0, self::MAX_BODY_LENGTH) . '"..."';
        try {
            return json_decode($truncated, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $truncated;
        }
    }

    private function shouldMaskKey(string $keyLower): bool
    {
        $sensitiveKeywords = [
            'password',
            'passwd',
            'cookie',
            'xsrf',
            'authorization',
            'token',
            'email',
            'passcode',
            'verification_code',
            'invite_code',
            'current_password',
            'profiles',
        ];

        return in_array($keyLower, $sensitiveKeywords, true) || Str::contains($keyLower, $sensitiveKeywords);
    }
}
