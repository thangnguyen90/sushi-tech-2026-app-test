<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoomAppointmentsIndexRequest;
use App\Repositories\LiveChatProfilesRepository;
use App\Repositories\MatchingUserRepository;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;

class RoomAppointmentsController extends Controller
{
    public function __construct(
        private readonly MatchingUserRepository $matchingUserRepository,
        private readonly LiveChatProfilesRepository $liveChatProfilesRepository,
        private readonly ResponseService $responseService,
    ) {}

    public function __invoke(RoomAppointmentsIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $language = (string) $request->header('language', 'jpn');
        $languageId = (int) (config('language.'.$language) ?? config('language.jpn', 1));
        $roomId = (int) $validated['room_id'];
        $userUuid = (string) $validated['user_uuid'];
        $visitor = $this->resolveVisitor($userUuid);

        $appointments = $this->matchingUserRepository->getApprovedAppointmentsForRoom(
            roomId: $roomId,
            userUuid: $userUuid,
            languageId: $languageId,
        );
        if ($appointments === []) {
            return $this->responseService->error(
                message: '商談予約をしていないユーザです',
                code: 'ROOM_APPOINTMENTS_NOT_FOUND',
                data: [
                    'visitor_name' => $visitor['name'],
                    'visitor' => $visitor,
                    'appointments' => [],
                ],
                status: 404,
            );
        }

        return $this->responseService->success(
            data: [
                'visitor_name' => $visitor['name'],
                'visitor' => $visitor,
                'appointments' => $appointments,
            ],
            code: 'OK',
            message: '',
        );
    }

    /**
     * @return array{
     *     user_uuid: string,
     *     user_id: int|null,
     *     name: string|null,
     *     email: string|null,
     *     company_name: string|null
     * }
     */
    private function resolveVisitor(string $userUuid): array
    {
        $profile = $this->liveChatProfilesRepository->getProfileByUserUuid($userUuid);

        return [
            'user_uuid' => (string) ($profile?->user_uuid ?? $userUuid),
            'user_id' => is_numeric($profile?->user_id) ? (int) $profile->user_id : null,
            'name' => is_string($profile?->nickname) && trim($profile->nickname) !== '' ? trim($profile->nickname) : null,
            'email' => is_string($profile?->mail_address) && trim($profile->mail_address) !== '' ? trim($profile->mail_address) : null,
            'company_name' => is_string($profile?->company) && trim($profile->company) !== '' ? trim($profile->company) : null,
        ];
    }
}
