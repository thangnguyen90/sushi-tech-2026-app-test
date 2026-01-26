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
    public function addOrUpdateDataFromWebhook(array $data ): void
    {
       $this->updateOrCreate(
           [
               'exhibitor_schedule_id' => $data['exhibitor_administrator_appointment_schedule_detail']['id'],
           ],
           [
               'user_id' => $data['applicant']['user']['user_id'],
               'user_uuid' => $data['applicant']['user']['user_uuid'],
               'started_at' => $data['exhibitor_administrator_appointment_schedule_detail']['schedule_start_datetime'],
               'data' => json_encode($data, JSON_THROW_ON_ERROR),
           ]
       );
    }
}
