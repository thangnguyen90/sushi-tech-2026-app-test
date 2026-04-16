<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoomShowRequest;
use App\Repositories\BusinessAppointmentRoomRepository;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;

class RoomShowController extends Controller
{
    public function __construct(
        private readonly BusinessAppointmentRoomRepository $businessAppointmentRoomRepository,
        private readonly ResponseService $responseService,
    ) {}

    public function __invoke(RoomShowRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $language = (string) $request->header('language', 'jpn');
        $languageId = (int) (config('language.'.$language) ?? config('language.jpn', 1));
        $fallbackLanguageId = (int) config('language.jpn', 1);
        $roomId = (int) $validated['room_id'];

        $room = $this->businessAppointmentRoomRepository->findLocalizedRoomByBusinessAppointmentRoomId(
            roomId: $roomId,
            languageId: $languageId,
            fallbackLanguageId: $fallbackLanguageId,
        );

        if ($room === null) {
            return $this->responseService->error(
                message: '商談場所が存在しません',
                code: 'ROOM_NOT_FOUND',
                data: [
                    'room_id' => $roomId,
                    'is_free' => false,
                    'room_name' => null,
                ],
                status: 404,
            );
        }

        return $this->responseService->success(
            data: [
                'room_id' => $roomId,
                'is_free' => (bool) $room->is_free,
                'room_name' => (string) $room->name,
            ],
            code: 'OK',
            message: '',
        );
    }
}
