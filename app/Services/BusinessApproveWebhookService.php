<?php

namespace App\Services;

class BusinessApproveWebhookService
{
    public function __construct(
        private readonly AppointmentWebhookSyncService $appointmentWebhookSyncService,
    ) {}

    public function process(array $data): void
    {
        $this->appointmentWebhookSyncService->process($data);
    }
}
