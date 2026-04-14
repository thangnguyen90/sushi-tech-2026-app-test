<?php

namespace App\Services;

use App\Repositories\BizTalksRepository;
use App\Repositories\MatchingUserRepository;
use Illuminate\Support\Facades\Log;

class AppointmentWebhookSyncService
{
    public function __construct(
        private readonly MatchingUserRepository $matchingUserRepository,
        private readonly BizTalksRepository $bizTalksRepository,
    ) {}

    public function process(array $data): void
    {
        $moduleCode = (string) ($data['module_code'] ?? '');

        if (! in_array($moduleCode, $this->supportedModuleCodes(), true)) {
            return;
        }

        try {
            $this->bizTalksRepository->addOrUpdateDataFromWebhook($data);

            if ($moduleCode === 'BusinessAppointmentApproved') {
                $this->matchingUserRepository->addOrUpdateDataFromWebhook($data);
            }
        } catch (\Throwable $throwable) {
            Log::error('Failed to sync appointment webhook.', [
                'module_code' => $data['module_code'] ?? null,
                'appointment_schedule_id' => data_get(
                    $data,
                    'exhibitor_administrator_appointment_schedule_detail.id'
                ),
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function supportedModuleCodes(): array
    {
        return [
            'BusinessAppointment',
            'BusinessAppointmentApproved',
            'BusinessAppointmentReject',
            'BusinessAppointmentCancel',
            'BusinessAppointmentReschedule',
        ];
    }
}
