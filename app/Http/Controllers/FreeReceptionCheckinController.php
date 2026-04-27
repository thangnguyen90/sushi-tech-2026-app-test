<?php

namespace App\Http\Controllers;

use App\Http\Requests\FreeReceptionCheckinRequest;
use App\Models\ReceptionCheckin;
use App\Repositories\BusinessAppointmentRoomRepository;
use App\Services\Eventos\User\EventosUserLookupService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class FreeReceptionCheckinController extends Controller
{
    public function __construct(
        private readonly BusinessAppointmentRoomRepository $businessAppointmentRoomRepository,
        private readonly EventosUserLookupService $eventosUserLookupService,
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

        try {
            $firstVisitor = $this->eventosUserLookupService->resolveByUuid($firstUserUuid);
            $secondVisitor = $this->eventosUserLookupService->resolveByUuid($secondUserUuid);
        } catch (Throwable) {
            return $this->responseService->error(
                message: '来場者情報の取得に失敗しました',
                code: 'RECEPTION_USER_LOOKUP_FAILED',
                data: [
                    'first_user_uuid' => $firstUserUuid,
                    'second_user_uuid' => $secondUserUuid,
                ],
                status: 500,
            );
        }

        if (! $this->hasResolvedVisitor($firstVisitor) || ! $this->hasResolvedVisitor($secondVisitor)) {
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

        $payload = DB::transaction(function () use ($roomId, $firstVisitor, $secondVisitor): array {
            $timestamp = now();

            $checkin = ReceptionCheckin::query()->create([
                'appointment_schedule_id' => null,
                'business_appointment_room_id' => $roomId,
                'applicant_user_id' => $firstVisitor['user_id'],
                'applicant_user_uuid' => $firstVisitor['user_uuid'],
                'recipient_user_id' => $secondVisitor['user_id'],
                'recipient_user_uuid' => $secondVisitor['user_uuid'],
                'user_uuid' => $secondVisitor['user_uuid'],
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

    /**
     * @param  array{user_uuid: string, user_id: int|null, name: string|null, email: string|null, company_name: string|null}  $visitor
     */
    private function hasResolvedVisitor(array $visitor): bool
    {
        return $visitor['user_id'] !== null
            || $visitor['name'] !== null
            || $visitor['email'] !== null
            || $visitor['company_name'] !== null;
    }
}
