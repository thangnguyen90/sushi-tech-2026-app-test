<?php

namespace App\Http\Controllers;

use App\Http\Requests\FreeReceptionCheckinRequest;
use App\Models\ReceptionCheckin;
use App\Repositories\BusinessAppointmentRoomRepository;
use App\Repositories\LiveChatProfilesRepository;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FreeReceptionCheckinController extends Controller
{
    public function __construct(
        private readonly BusinessAppointmentRoomRepository $businessAppointmentRoomRepository,
        private readonly LiveChatProfilesRepository $liveChatProfilesRepository,
        private readonly ResponseService $responseService,
    ) {}

    public function __invoke(FreeReceptionCheckinRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $roomId = (int) $validated['room_id'];
        $firstUserUuid = (string) $validated['first_user_uuid'];
        $secondUserUuid = (string) $validated['second_user_uuid'];

        if (! $this->businessAppointmentRoomRepository->isFreeRoom($roomId)) {
            return $this->responseService->error(
                message: 'フリー受付対象の商談場所ではありません',
                code: 'ROOM_IS_NOT_FREE',
                data: [
                    'room_id' => $roomId,
                ],
                status: 422,
            );
        }

        $firstProfile = $this->liveChatProfilesRepository->getProfileByUserUuid($firstUserUuid);
        $secondProfile = $this->liveChatProfilesRepository->getProfileByUserUuid($secondUserUuid);

        if ($firstProfile === null || $secondProfile === null) {
            return $this->responseService->error(
                message: '来場者が見つかりません',
                code: 'RECEPTION_USER_NOT_FOUND',
                data: [
                    'first_user_uuid' => $firstUserUuid,
                    'second_user_uuid' => $secondUserUuid,
                ],
                status: 404,
            );
        }

        $payload = DB::transaction(function () use ($roomId, $firstProfile, $secondProfile): array {
            $timestamp = now();

            $checkin = ReceptionCheckin::query()->create([
                'appointment_schedule_id' => null,
                'business_appointment_room_id' => $roomId,
                'applicant_user_id' => is_numeric($firstProfile->user_id) ? (int) $firstProfile->user_id : null,
                'applicant_user_uuid' => (string) $firstProfile->user_uuid,
                'recipient_user_id' => is_numeric($secondProfile->user_id) ? (int) $secondProfile->user_id : null,
                'recipient_user_uuid' => (string) $secondProfile->user_uuid,
                'user_uuid' => (string) $secondProfile->user_uuid,
                'checkin_status' => 'second_checkin',
                'first_checkin_at' => $timestamp,
                'second_checkin_at' => $timestamp,
                'checkin_at' => $timestamp,
            ]);

            return [
                'room_id' => $roomId,
                'checkin_status' => 'second_checkin',
                'checkin_at' => $timestamp->format('Y-m-d H:i:s'),
                'first_checkin_at' => $timestamp->format('Y-m-d H:i:s'),
                'second_checkin_at' => $timestamp->format('Y-m-d H:i:s'),
                'user_uuid' => (string) $checkin->user_uuid,
                'applicant_user_id' => is_numeric($checkin->applicant_user_id) ? (int) $checkin->applicant_user_id : null,
                'applicant_user_uuid' => $checkin->applicant_user_uuid,
                'recipient_user_id' => is_numeric($checkin->recipient_user_id) ? (int) $checkin->recipient_user_id : null,
                'recipient_user_uuid' => $checkin->recipient_user_uuid,
            ];
        });

        return $this->responseService->success(
            data: $payload,
            code: 'OK',
            message: '',
        );
    }
}
