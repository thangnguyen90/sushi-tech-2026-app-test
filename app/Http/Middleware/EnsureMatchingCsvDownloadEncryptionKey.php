<?php

namespace App\Http\Middleware;

use App\Models\MatchingCsvDownloadSetting;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMatchingCsvDownloadEncryptionKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestEncryptionKey = trim((string) $request->header('x-encryption-key', ''));

        if ($requestEncryptionKey === '') {
            return new JsonResponse([
                'message' => 'Missing x-encryption-key',
                'error' => 'Unauthorized',
            ], 401);
        }

        $setting = MatchingCsvDownloadSetting::query()
            ->latest('id')
            ->first();

        $configuredEncryptionKey = trim((string) ($setting?->encryption_key ?? ''));

        if ($configuredEncryptionKey === '' || ! hash_equals($configuredEncryptionKey, $requestEncryptionKey)) {
            return new JsonResponse([
                'message' => 'Invalid x-encryption-key',
                'error' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
