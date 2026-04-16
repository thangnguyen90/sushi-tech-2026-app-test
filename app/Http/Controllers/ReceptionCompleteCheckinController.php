<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceptionCompleteCheckinRequest;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;

class ReceptionCompleteCheckinController extends Controller
{
    public function __construct(
        private readonly ResponseService $responseService,
    ) {}

    public function __invoke(ReceptionCompleteCheckinRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return $this->responseService->success(
            data: $this->mockCompletedCheckinResult((string) $validated['appointment_id']),
            code: 'OK',
            message: ''
        );
    }

    /**
     * @return array{
     *     visitor_name: string,
     *     partner_name: string,
     *     appointment_id: string,
     *     time_label: string
     * }
     */
    private function mockCompletedCheckinResult(string $appointmentId): array
    {
        return match ($appointmentId) {
            '23351' => [
                'visitor_name' => '中井颯人',
                'partner_name' => 'Taro Sato',
                'appointment_id' => '23351',
                'time_label' => '2026/04/28 16:00 ~ 16:30',
            ],
            default => [
                'visitor_name' => '中井颯人',
                'partner_name' => 'Taro Tokyo',
                'appointment_id' => '12345',
                'time_label' => '2026/04/28 10:00 ~ 11:00',
            ],
        };
    }
}
