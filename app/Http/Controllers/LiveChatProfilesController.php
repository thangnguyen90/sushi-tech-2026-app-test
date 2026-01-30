<?php

namespace App\Http\Controllers;

use App\Repositories\UsersRepository;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use App\Repositories\LiveChatProfilesRepository;

class LiveChatProfilesController extends Controller
{

    public function __construct(
        private readonly UsersRepository    $usersRepository,
        private readonly ResponseService    $responseService
    )
    {
    }

    public function checkUserFistLoginAndAgreePolicy(Request $request) : \Illuminate\Http\JsonResponse
    {
        $isFirstLogin = true;
        $isAgreed = false;
        $user = $this->usersRepository->firstWhere('uuid', $request->header('user-uuid') );

        if (!$user) {
            $this->usersRepository->create([
                'uuid' => $request->header('user-uuid'),
                'policy_agreed' => false,
            ]);
        }else{
            $isFirstLogin = false;
            $isAgreed = $user->policy_agreed;
        }

        return $this->responseService->success(
            data: [
                'is_first_login' => $isFirstLogin,
                'policy_agreed' => $isAgreed,
            ],
        );
    }

    public function userAgreement( Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $this->usersRepository->firstWhere('uuid', $request->header('user-uuid'));
        $user->policy_agreed = true;
        $user->save();
        return $this->responseService->success();
    }
}
