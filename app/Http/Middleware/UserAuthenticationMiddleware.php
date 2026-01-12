<?php

namespace App\Http\Middleware;

use App\Repositories\LiveChatProfilesRepository;
use Closure;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class UserAuthenticationMiddleware
{
    protected LiveChatProfilesRepository $liveChatProfilesRepository;

    public function __construct(LiveChatProfilesRepository $liveChatProfilesRepository)
    {
        $this->liveChatProfilesRepository = $liveChatProfilesRepository;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userUuid = $request->header('user-uuid');
        if (!$userUuid) {
            return new JsonResponse(['message' => 'Missing user uuid'], 401);
        }
        if (Str::isUuid($userUuid)) {
            try {
                $user = $this->liveChatProfilesRepository->query()
                    ->where('uuid', $userUuid)
                    ->first();
                if (!$user) {
                    return new JsonResponse(['message' => 'Unauthorized', 'error' => 'Unauthorized'], 401);
                }
                Auth::login($user);
                return $next($request);
            } catch (\Throwable $e) {
                Log::error($e);
                return new JsonResponse(['message' => $e->getMessage(), 'error' => 'Unauthorized'], 401);
            }
        } else {
            return new JsonResponse(['message' => 'Unauthorized', 'error' => 'Unauthorized'], 401);
        }
    }
}
