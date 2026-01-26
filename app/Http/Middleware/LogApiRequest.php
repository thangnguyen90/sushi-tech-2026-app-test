<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use JsonException;

class LogApiRequest
{
    private const int MAX_BODY_LENGTH = 20000; // max log body size in bytes

    /**
     * @throws JsonException
     */
    public function handle(Request $request, Closure $next)
    {
        if(app()->environment('product', 'production', 'prod') || !config('app.debug')) {
            return $next($request);
        }
        $requestId = $this->getOrCreateRequestId($request);

        // add request_id to log context (tùy driver)
        Log::withContext([
            'request_id' => $requestId,
            'method'     => $request->method(),
            'path'       => $request->path(),
        ]);

        $headers = $this->sanitizeHeaders($request->headers->all());
        $body    = $this->extractBody($request);

        Log::info('request', [
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'headers'     => $headers,
            'query'       => $this->sanitizeArray($request->query()),
            'body'        => $body,
        ]);

        $response = $next($request);

        // log response basic info (optional)
        Log::info('api.response', [
            'status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
        ]);

        // set a response header for tracing
        if (method_exists($response, 'headers')) {
            $response->headers->set('X-Request-Id', $requestId);
        }

        return $response;
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
        // Ưu tiên parse theo content-type
        if ($request->isJson()) {
            $data = $request->json()?->all();
            return $this->limitSize($this->sanitizeArray($data));
        }
        $data = $request->all();

        // Nếu upload file, chỉ log metadata (không log binary)
        if (!empty($request->allFiles())) {
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
        // headers->all() trả về array values
        $flat = array_map(static function ($v) {
            return is_array($v) ? implode(', ', $v) : (string)$v;
        }, $headers);

        return $this->sanitizeArray($flat);
    }

    private function sanitizeArray(array $data): array
    {
        $masked = [];
        foreach ($data as $key => $value) {
//            $keyLower = strtolower((string) $key);

//            if ($this->shouldMaskKey($keyLower)) {
//                $masked[$key] = '***';
//                continue;
//            }

            if (is_array($value)) {
                $masked[$key] = $this->sanitizeArray($value);
                continue;
            }

            // string quá dài thì cắt
            if (is_string($value) && mb_strlen($value) > 2000) {
                $masked[$key] = mb_substr($value, 0, 2000) . '...';
                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }

//    a private function shouldMaskKey(string $keyLower): bool
//    {
//        return false;
//    }

    /**
     * @throws JsonException
     */
    private function limitSize($data)
    {
        // ép về json string để giới hạn tổng kích thước log
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return $data;
        }

        if (strlen($json) <= self::MAX_BODY_LENGTH) {
            return $data;
        }

        $truncated = substr($json, 0, self::MAX_BODY_LENGTH) . '"..."';
        $decoded = json_decode($truncated, true, 512, JSON_THROW_ON_ERROR);

        return $decoded ?? $truncated;
    }
}
