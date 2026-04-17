<?php

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Models\BizTalks;
use JsonException;

class BizTalksRepository extends BaseRepository
{

    /**
     * @inheritDoc
     */
    protected function modelClass(): string
    {
        return BizTalks::class;
    }

    public function newUniqueId(): string
    {
        return (string) BizTalks::newUniqueId();
    }

    /**
     * @throws JsonException
     */
    public function addOrUpdateDataFromWebhook(array $data): void
    {
        // Extract applicant user_id and user_uuid based on type (user or exhibitor)
        $applicantUserId = $data['applicant']['user_id']
            ?? $data['applicant']['user']['user_id']
            ?? $data['applicant']['exhibitor_administrator_id']
            ?? null;
        $applicantUuid = $data['applicant']['user_uuid']
            ?? $data['applicant']['user']['user_uuid']
            ?? $data['applicant']['exhibitor_administrator_uuid']
            ?? null;

        // Validate that we have required data
        if (!$applicantUserId || !$applicantUuid) {
            return;
        }

        $this->updateOrCreate(
            [
                'exhibitor_schedule_id' => $data['exhibitor_administrator_appointment_schedule_detail']['id'],
            ],
            [
                'user_id' => $applicantUserId,
                'user_uuid' => $applicantUuid,
                'started_at' => \Illuminate\Support\Carbon::parse(
                    $data['exhibitor_administrator_appointment_schedule_detail']['schedule_start_datetime'],
                    'Asia/Tokyo'
                )->utc()->format('Y-m-d H:i:s'),
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
            ]
        );
    }
}
