<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppointmentCheckinRequest;
use App\Models\ReceptionCheckin;
use App\Repositories\MatchingUserRepository;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AppointmentCheckinController extends Controller
{
    public function __construct(
        private readonly MatchingUserRepository $matchingUserRepository,
        private readonly ResponseService $responseService,
    ) {}

    public function __invoke(AppointmentCheckinRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $appointmentId = (int) $validated['appointment_id'];
        $userUuid = (string) $validated['user_uuid'];

        $appointment = $this->matchingUserRepository->findApprovedAppointmentByScheduleId($appointmentId);

        if (! $appointment) {
            return $this->responseService->error(
                message: '商談予約が見つかりません',
                code: 'APPOINTMENT_NOT_FOUND',
                data: null,
                status: 404,
            );
        }

        $payload = DB::transaction(function () use ($appointment, $appointmentId, $userUuid): array {
            $checkin = ReceptionCheckin::query()
                ->where('appointment_schedule_id', $appointmentId)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $now = now();

            if ($checkin === null) {
                $checkin = ReceptionCheckin::query()->create([
                    'appointment_schedule_id' => $appointmentId,
                    'business_appointment_room_id' => $appointment->business_appointment_room_id,
                    'applicant_user_id' => $appointment->owner_user_id,
                    'applicant_user_uuid' => $appointment->owner_uuid,
                    'recipient_user_id' => $appointment->peer_user_id,
                    'recipient_user_uuid' => $appointment->peer_uuid,
                    'user_uuid' => $userUuid,
                    'checkin_status' => 'first_checkin',
                    'first_checkin_at' => $now,
                    'second_checkin_at' => null,
                    'checkin_at' => $now,
                ]);
            } else {
                $checkin->fill([
                    'business_appointment_room_id' => $appointment->business_appointment_room_id,
                    'applicant_user_id' => $appointment->owner_user_id,
                    'applicant_user_uuid' => $appointment->owner_uuid,
                    'recipient_user_id' => $appointment->peer_user_id,
                    'recipient_user_uuid' => $appointment->peer_uuid,
                ]);

                if ($checkin->checkin_status === 'second_checkin') {
                    $checkin->checkin_at = $now;
                } elseif ($checkin->user_uuid === $userUuid) {
                    $checkin->checkin_status = 'first_checkin';
                    $checkin->first_checkin_at = $now;
                    $checkin->checkin_at = $now;
                } else {
                    $firstAt = $checkin->first_checkin_at ?? $checkin->checkin_at;
                    $checkin->user_uuid = $userUuid;
                    $checkin->checkin_status = 'second_checkin';
                    $checkin->first_checkin_at = $firstAt;
                    $checkin->second_checkin_at = $now;
                    $checkin->checkin_at = $now;
                }

                $checkin->save();
            }

            return $this->buildResponsePayload($checkin);
        });

        return $this->responseService->success(
            data: $payload,
            code: 'OK',
            message: '',
        );
    }

    /**
     * @return array{
     *     appointment_id: int,
     *     checkin_status: string,
     *     user_uuid: string,
     *     checkin_at: string|null,
     *     first_checkin_at: string|null,
     *     second_checkin_at: string|null,
     *     applicant_user_id: int|null,
     *     applicant_user_uuid: string|null,
     *     recipient_user_id: int|null,
     *     recipient_user_uuid: string|null
     * }
     */
    private function buildResponsePayload(ReceptionCheckin $checkin): array
    {
        return [
            'appointment_id' => (int) $checkin->appointment_schedule_id,
            'checkin_status' => (string) $checkin->checkin_status,
            'user_uuid' => (string) $checkin->user_uuid,
            'checkin_at' => $checkin->checkin_at?->format('Y-m-d H:i:s'),
            'first_checkin_at' => $checkin->first_checkin_at?->format('Y-m-d H:i:s'),
            'second_checkin_at' => $checkin->second_checkin_at?->format('Y-m-d H:i:s'),
            'applicant_user_id' => is_numeric($checkin->applicant_user_id) ? (int) $checkin->applicant_user_id : null,
            'applicant_user_uuid' => $checkin->applicant_user_uuid,
            'recipient_user_id' => is_numeric($checkin->recipient_user_id) ? (int) $checkin->recipient_user_id : null,
            'recipient_user_uuid' => $checkin->recipient_user_uuid,
        ];
    }
}
