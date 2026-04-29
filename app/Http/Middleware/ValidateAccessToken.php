<?php

namespace App\Http\Middleware;

use App\Exceptions\UnauthorizedException;
use App\Services\Eventos\User\AccessTokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateAccessToken
{
    public function __construct(
        private readonly AccessTokenService $accessTokenService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {

        $token = $request->header('access-token');

        if (empty($token)) {
            return new JsonResponse(['message' => 'Missing access-token'], 401);
        }

        try {
            $user = $this->accessTokenService->validate($token);
        } catch (UnauthorizedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 401);
        } catch (\RuntimeException $e) {
            $status = $e->getCode();
            $status = ($status >= 400 && $status < 600) ? $status : 503;
            return new JsonResponse(['message' => $e->getMessage()], $status);
        }

        $request->attributes->set('access_token_user_uuid', $user['data']['user_uuid'] ?? null);

        return $next($request);
    }
}
