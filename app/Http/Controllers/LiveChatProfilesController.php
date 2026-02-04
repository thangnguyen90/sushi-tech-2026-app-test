<?php

namespace App\Http\Controllers;

use App\Repositories\UsersRepository;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use TypeError;

class LiveChatProfilesController extends Controller
{
    private const string USER_UUID_HEADER = 'user-uuid';

    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly ResponseService $responseService,
    ) {
    }

    public function checkUserFistLoginAndAgreePolicy(Request $request): JsonResponse
    {
        try {
            $uuid = $this->getUuidFromRequest($request);

            // Requirement: if uuid is missing or user not found => policy_agreed = true
            if ($uuid === null) {
                return $this->responseService->error(
                    message: 'Failed to check user login and agreement status.',
                    status: 500,
                );
            }

            $user = $this->findUserByUuid($uuid);
            if ($user === null) {
                return $this->responseService->error(
                    message: 'Failed to check user login and agreement status.',
                );
            }

            $isFirstLogin = $this->consumeFirstLoginFlag($user);
            $isAgreed = (bool) $user->policy_agreed;

            return $this->responseService->success(data: [
                'is_first_login' => $isFirstLogin,
                'policy_agreed' => $isAgreed,
            ]);
        } catch (Exception|TypeError $e) {
            return $this->responseService->error(
                message: 'Failed to check user login and agreement status.',
                status: 500,
            );
        }
    }

    public function userAgreement(Request $request): JsonResponse
    {
        try {
            $uuid = $this->getUuidFromRequest($request);

            // Same behavior: if uuid is missing or user not found => treat as already agreed
            if ($uuid === null) {
                return $this->responseService->error(
                    message: 'Failed to check user login and agreement status.',
                    status: 403,
                );
            }

            $user = $this->findUserByUuid($uuid);
            if ($user === null) {
                return $this->responseService->error(
                    message: 'Failed to check user login and agreement status.',
                    status: 403,
                );
            }

            // Keep first-login behavior consistent across endpoints.
            $isFirstLogin = $this->consumeFirstLoginFlag($user);

            // Only this endpoint updates policy_agreed.
            if (!$user->policy_agreed) {
                $user->policy_agreed = true;
                $user->save();
            }

            return $this->responseService->success(data: [
                'is_first_login' => $isFirstLogin,
                'policy_agreed' => true,
            ]);
        } catch (Exception|TypeError $e) {
            return $this->responseService->error(
                message: 'Failed to record user agreement.',
                status: 500,
            );
        }
    }

    private function getUuidFromRequest(Request $request): ?string
    {
        $uuid = $request->header(self::USER_UUID_HEADER);
        if (!is_string($uuid)) {
            return null;
        }

        $uuid = trim($uuid);
        return $uuid !== '' ? $uuid : null;
    }

    private function findUserByUuid(string $uuid): mixed
    {
        $user = $this->usersRepository->firstWhere('uuid', $uuid);
        return $user ?: null;
    }

    private function consumeFirstLoginFlag(mixed $user): bool
    {
        $wasFirstLogin = (bool) $user->is_first_login;

        if ($wasFirstLogin) {
            $user->is_first_login = false;
            $user->save();
        }

        return $wasFirstLogin;
    }
}
