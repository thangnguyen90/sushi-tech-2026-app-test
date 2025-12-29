<?php

namespace App\Http\Controllers;

use App\Services\ResponseService;
use Illuminate\Http\Request;
use App\Repositories\UserRepository;

class UserController extends Controller
{

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ResponseService $responseService
    )
    {
    }

    public function checkUserFistLoginAndAgreePolicy(Request $request) : \Illuminate\Http\JsonResponse
    {
        $isFirstLogin = true;
        $isAgreed = false;
        $user = $this->userRepository->firstWhere('uuid', $request->header('user-uuid') );

        if (!$user) {
            $this->userRepository->create([
                'uuid' => $request->header('user-uuid'),
                'is_agreed' => false,
            ]);
        }else{
            $isFirstLogin = false;
            $isAgreed = $user->is_agreed;
        }

        return $this->responseService->success(
            data: [
                'first_login' => $isFirstLogin,
                'policy_agreed' => $isAgreed,
            ],
        );


    }
}
