<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceptionCheckinRequest;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;

class ReceptionCheckinController extends Controller
{
    private const string NO_DATA_QR_CODE = 'QR-NO-DATA-DEMO';

    public function __construct(
        private readonly ResponseService $responseService,
    ) {}

    public function __invoke(ReceptionCheckinRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return $this->responseService->success(
            data: $this->mockCheckinResult((string) $validated['qr_code']),
            code: 'OK',
            message: ''
        );
    }

    /**
     * @return array{
     *     visitor_name: string,
     *     appointments: array<int, array{
     *         appointment_id: string,
     *         time_label: string,
     *         location_label: string,
     *         partner_name: string,
     *         can_checkin: bool,
     *         action_label: string
     *     }>
     * }
     */
    private function mockCheckinResult(string $qrCode): array
    {
        if ($qrCode === self::NO_DATA_QR_CODE) {
            return [
                'visitor_name' => '中井颯人',
                'appointments' => [],
            ];
        }

        return [
            'visitor_name' => '中井颯人',
            'appointments' => [
                [
                    'appointment_id' => '12345',
                    'time_label' => '2026/04/28 10:00 ~ 10:30',
                    'location_label' => '商談エリアB（1階）',
                    'partner_name' => 'Taro Tokyo',
                    'can_checkin' => true,
                    'action_label' => 'この商談をチェックインする',
                ],
                [
                    'appointment_id' => '15432',
                    'time_label' => '2026/04/28 16:00 ~ 16:30',
                    'location_label' => '商談エリアD（1階）',
                    'partner_name' => 'Hanako Yamada',
                    'can_checkin' => false,
                    'action_label' => '別の商談場所が予約されています',
                ],
                [
                    'appointment_id' => '23351',
                    'time_label' => '2026/04/28 16:00 ~ 16:30',
                    'location_label' => '商談エリアB（1階）',
                    'partner_name' => 'Taro Sato',
                    'can_checkin' => true,
                    'action_label' => 'この商談をチェックインする',
                ],
            ],
        ];
    }
}
