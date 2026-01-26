<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Repositories\BizTalksRepository;
use App\Repositories\MatchingUserRepository;
class BusinessApproveWebhookService
{
    private BizTalksRepository $bizTalksRepository;
    private MatchingUserRepository $matchingUserRepository;
    const string MODULE = 'BusinessAppointmentApproved';
    public function __construct(BizTalksRepository $bizTalksRepository, MatchingUserRepository $matchingUserRepository)
    {
        $this->bizTalksRepository = $bizTalksRepository;
        $this->matchingUserRepository = $matchingUserRepository;
    }
    public function process(array $data): void
    {
        // Implement the business approval logic here
        // For example, update database records based on the webhook data
        try {
            if($data['module_code'] !== self::MODULE) {
                return;
            }
            $exhibitorAdministratorInfo = [
                'exhibitor_id' => $data['recipient']['exhibitor_administrator']['exhibitor_id'],
                'exhibitor_administrator_id' => $data['recipient']['exhibitor_administrator']['exhibitor_administrator_id'],
                'exhibitor_administrator_uuid' => $data['recipient']['exhibitor_administrator']['exhibitor_administrator_uuid'],
            ];
            unset(
                $data['module_code'],
                $data['exhibitor_administrator_appointment_schedule_detail']['approval_message'],
                $data['exhibitor_administrator_appointment_schedule_detail']['cancel_message'],
                $data['exhibitor_administrator_appointment_schedule_detail']['user_mail_address'],
                $data['exhibitor_administrator_appointment_schedule_detail']['user_message'],
                $data['exhibitor_administrator_appointment_schedule_detail']['applicant_email'],
                $data['exhibitor_administrator_appointment_schedule_detail']['recipient_email'],
                $data['applicant']['user']['auth_account'],
                $data['applicant']['user']['share_profiles'],
                $data['applicant']['user']['event_profiles'],
                $data['recipient']['exhibitor_administrator']
            );
            $data['recipient'] = $exhibitorAdministratorInfo;
            $this->matchingUserRepository->addOrUpdateDataFromWebhook($data);
            $this->bizTalksRepository->addOrUpdateDataFromWebhook($data);
            return;
        }catch (\Throwable $e) {
            // Log the error or handle it as needed
            Log::error($e);
            return;
        }
    }
}
