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
use App\Repositories\UsersRepository;
use App\Services\Eventos\User\UserService;

class UserAuthenticationMiddleware
{
    protected LiveChatProfilesRepository $liveChatProfilesRepository;

    public function __construct(LiveChatProfilesRepository $liveChatProfilesRepository,
    private readonly UsersRepository $usersRepository,
    private readonly UserService $userService,
    )
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
                $user = $this->usersRepository->query()
                    ->where('uuid', $userUuid)
                    ->first();
                if(empty($user) || empty($user->user_id)){
                    return new JsonResponse(['message' => 'Unauthorized', 'error' => 'Unauthorized'], 401);
                }
                $userProfile = $this->liveChatProfilesRepository->getProfileByUserId($user->user_id);
                Auth::login($userProfile);
                return $next($request);
            } catch (\Throwable $e) {
                Log::error($e);
                return new JsonResponse(['message' => $e->getMessage(), 'error' => 'Unauthorized'], 401);
            }
        } else {
            return new JsonResponse(['message' => 'Unauthorized', 'error' => 'Unauthorized'], 401);
        }
    }

    private function getIdLiveChatProfile($uuid): ?string{
        $dataUser = $this->userService->getUsersByUuid($uuid);
        if(empty($dataUser['account'])){
            $dataUser['account'] = null;
        }else{
            $liveChatProfile = $this->liveChatProfilesRepository->firstWhere('mail_address', $dataUser['account']);
            $dataUser['account'] = $liveChatProfile ? $liveChatProfile->user_id : $liveChatProfile->exhibitor_administrator_id ;
        }
        return $dataUser['account'];
    }
}
