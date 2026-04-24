<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceptionUserShowRequest;
use App\Repositories\LiveChatProfilesRepository;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;

class ReceptionUserShowController extends Controller
{
    public function __construct(
        private readonly LiveChatProfilesRepository $liveChatProfilesRepository,
        private readonly ResponseService $responseService,
    ) {}

    public function __invoke(ReceptionUserShowRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userUuid = (string) $validated['user_uuid'];
        $profile = $this->liveChatProfilesRepository->getProfileByUserUuid($userUuid);

        if ($profile === null) {
            return $this->responseService->error(
                message: '来場者が見つかりません',
                code: 'RECEPTION_USER_NOT_FOUND',
                data: [
                    'user_uuid' => $userUuid,
                ],
                status: 404,
            );
        }

        return $this->responseService->success(
            data: [
                'user_uuid' => (string) $profile->user_uuid,
                'user_id' => is_numeric($profile->user_id) ? (int) $profile->user_id : null,
                'name' => is_string($profile->nickname) && trim($profile->nickname) !== '' ? trim($profile->nickname) : null,
                'email' => is_string($profile->mail_address) && trim($profile->mail_address) !== '' ? trim($profile->mail_address) : null,
                'company_name' => is_string($profile->company) && trim($profile->company) !== '' ? trim($profile->company) : null,
            ],
            code: 'OK',
            message: '',
        );
    }
}
