<?php

namespace App\Http\Middleware;

use App\Models\CsvDownloadLog;
use App\Services\CsvDownloadAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogCsvDownloadRequest
{
    public function __construct(
        private readonly CsvDownloadAuditService $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->writeLog($request, $response);

        return $response;
    }

    private function writeLog(Request $request, Response $response): void
    {
        try {
            $statusCode  = $response->getStatusCode();
            $userUuid    = $request->attributes->get('access_token_user_uuid');
            $ip          = $request->ip();
            $userUuids   = $request->input('live_chat_user_uuids', []);
            $status      = $this->resolveStatus($statusCode);

            // Chỉ detect suspicious flags khi request đi qua được (auth + rate limit pass)
            $flags = $statusCode === 200
                ? $this->audit->detectFlags($ip, $userUuid, $userUuids)
                : [];

            CsvDownloadLog::create([
                'user_uuid'             => $userUuid,
                'ip_address'            => $ip,
                'downloaded_user_count' => count($userUuids),
                'live_chat_user_uuids'  => ! empty($userUuids) ? $userUuids : null,
                'is_suspicious'         => ! empty($flags),
                'suspicious_flags'      => ! empty($flags) ? $flags : null,
                'http_status_code'      => $statusCode,
                'status'                => $status,
                'error_message'         => $statusCode >= 400
                    ? $this->extractErrorMessage($response)
                    : null,
            ]);

            $this->audit->alertIfSuspicious($ip, $userUuid, $flags);

        } catch (\Throwable) {
            // logging must never break the response
        }
    }

    private function resolveStatus(int $statusCode): string
    {
        return match (true) {
            $statusCode === 200, $statusCode === 201 => 'success',
            $statusCode === 401                      => 'unauthorized',
            $statusCode === 403                      => 'forbidden',
            $statusCode === 429                      => 'rate_limited',
            $statusCode === 503                      => 'service_unavailable',
            $statusCode >= 500                       => 'failed',
            default                                  => 'failed',
        };
    }

    private function extractErrorMessage(Response $response): ?string
    {
        try {
            $content = method_exists($response, 'getContent') ? $response->getContent() : null;
            if (! is_string($content) || $content === '') {
                return null;
            }
            $decoded = json_decode($content, true);
            $message = $decoded['message'] ?? null;
            return is_string($message) ? mb_substr($message, 0, 255) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
