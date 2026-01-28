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

            // Determine the scenario based on applicant and recipient types
            $applicantIsUser = isset($data['applicant']['user']);
            $applicantIsExhibitor = isset($data['applicant']['exhibitor_administrator']);
            $recipientIsUser = isset($data['recipient']['user']);
            $recipientIsExhibitor = isset($data['recipient']['exhibitor_administrator']);
//            dd($applicantIsUser, $applicantIsExhibitor, $recipientIsUser, $recipientIsExhibitor);
            // Process based on the scenario
            if ($applicantIsUser && $recipientIsUser) {
                // Scenario 1: user <> user
//                dd('user <> user');
                $this->processUserToUser($data);
            } elseif ($applicantIsUser && $recipientIsExhibitor) {
                // Scenario 2: user -> exhibitor
//                dd('user -> exhibitor');
                $this->processUserToExhibitor($data);
            } elseif ($applicantIsExhibitor && $recipientIsUser) {
                // Scenario 3: exhibitor -> user
//                dd('exhibitor -> user');
                $this->processExhibitorToUser($data);
            } elseif ($applicantIsExhibitor && $recipientIsExhibitor) {
                // Scenario 4: exhibitor <> exhibitor
//                dd('exhibitor <> exhibitor');
                $this->processExhibitorToExhibitor($data);
            }
            return;
        } catch (\Throwable $e) {
            // Log the error or handle it as needed
            Log::error($e);
            return;
        }
    }

    private function processUserToUser(array $data): void
    {
        // Extract user IDs before cleaning
        $applicantUserId = $data['applicant']['user']['user_id'];
        $applicantUuid = $data['applicant']['user']['user_uuid'];
        $recipientUserId = $data['recipient']['user']['user_id'];
        $recipientUuid = $data['recipient']['user']['user_uuid'];

        // Clean up unnecessary data for user-to-user scenario
        $cleanData = $this->cleanWebhookData($data, [
            'module_code',
            'exhibitor_administrator_appointment_schedule_detail.approval_message',
            'exhibitor_administrator_appointment_schedule_detail.cancel_message',
            'exhibitor_administrator_appointment_schedule_detail.user_mail_address',
            'exhibitor_administrator_appointment_schedule_detail.user_message',
            'exhibitor_administrator_appointment_schedule_detail.applicant_email',
            'exhibitor_administrator_appointment_schedule_detail.recipient_email',
            'applicant.user.auth_account',
            'applicant.user.share_profiles',
            'applicant.user.event_profiles',
            'recipient.user.auth_account',
            'recipient.user.share_profiles',
            'recipient.user.event_profiles',
        ]);

        // Set minimal user info for repositories
        $cleanData['applicant'] = [
            'user_id' => $applicantUserId,
            'user_uuid' => $applicantUuid,
        ];
        $cleanData['recipient'] = [
            'user_id' => $recipientUserId,
            'user_uuid' => $recipientUuid,
        ];
        $this->matchingUserRepository->addOrUpdateDataFromWebhook($cleanData);
        $this->bizTalksRepository->addOrUpdateDataFromWebhook($cleanData);
    }

    private function processUserToExhibitor(array $data): void
    {
        // Extract user and exhibitor information before cleaning
        $applicantUserId = $data['applicant']['user']['user_id'];
        $applicantUuid = $data['applicant']['user']['user_uuid'];

        // Extract exhibitor information from recipient
        $exhibitorAdministratorInfo = [
            'exhibitor_id' => $data['recipient']['exhibitor_administrator']['exhibitor_id'],
            'exhibitor_administrator_id' => $data['recipient']['exhibitor_administrator']['exhibitor_administrator_id'],
            'exhibitor_administrator_uuid' => $data['recipient']['exhibitor_administrator']['exhibitor_administrator_uuid'],
        ];

        // Clean up unnecessary data
        $cleanData = $this->cleanWebhookData($data, [
            'module_code',
            'exhibitor_administrator_appointment_schedule_detail.approval_message',
            'exhibitor_administrator_appointment_schedule_detail.cancel_message',
            'exhibitor_administrator_appointment_schedule_detail.user_mail_address',
            'exhibitor_administrator_appointment_schedule_detail.user_message',
            'exhibitor_administrator_appointment_schedule_detail.applicant_email',
            'exhibitor_administrator_appointment_schedule_detail.recipient_email',
            'applicant.user.auth_account',
            'applicant.user.share_profiles',
            'applicant.user.event_profiles',
            'recipient.exhibitor_administrator',
        ]);

        // Set minimal info for repositories
        $cleanData['applicant'] = [
            'user_id' => $applicantUserId,
            'user_uuid' => $applicantUuid,
        ];
        $cleanData['recipient'] = $exhibitorAdministratorInfo;

        $this->matchingUserRepository->addOrUpdateDataFromWebhook($cleanData);
        $this->bizTalksRepository->addOrUpdateDataFromWebhook($cleanData);
    }

    private function processExhibitorToUser(array $data): void
    {
        // Extract exhibitor and user information before cleaning
        $recipientUserId = $data['recipient']['user']['user_id'];
        $recipientUuid = $data['recipient']['user']['user_uuid'];

        // Extract exhibitor information from applicant
        $exhibitorAdministratorInfo = [
            'exhibitor_id' => $data['applicant']['exhibitor_administrator']['exhibitor_id'],
            'exhibitor_administrator_id' => $data['applicant']['exhibitor_administrator']['exhibitor_administrator_id'],
            'exhibitor_administrator_uuid' => $data['applicant']['exhibitor_administrator']['exhibitor_administrator_uuid'],
        ];

        // Clean up unnecessary data
        $cleanData = $this->cleanWebhookData($data, [
            'module_code',
            'exhibitor_administrator_appointment_schedule_detail.approval_message',
            'exhibitor_administrator_appointment_schedule_detail.cancel_message',
            'exhibitor_administrator_appointment_schedule_detail.user_mail_address',
            'exhibitor_administrator_appointment_schedule_detail.user_message',
            'exhibitor_administrator_appointment_schedule_detail.applicant_email',
            'exhibitor_administrator_appointment_schedule_detail.recipient_email',
            'applicant.exhibitor_administrator',
            'recipient.user.auth_account',
            'recipient.user.share_profiles',
            'recipient.user.event_profiles',
        ]);

        // Set minimal info for repositories
        $cleanData['applicant'] = $exhibitorAdministratorInfo;
        $cleanData['recipient'] = [
            'user_id' => $recipientUserId,
            'user_uuid' => $recipientUuid,
        ];

        $this->matchingUserRepository->addOrUpdateDataFromWebhook($cleanData);
        $this->bizTalksRepository->addOrUpdateDataFromWebhook($cleanData);
    }

    /**
     * @throws \JsonException
     */
    private function processExhibitorToExhibitor(array $data): void
    {
        // Extract exhibitor information from both applicant and recipient
        $applicantExhibitorInfo = [
            'exhibitor_id' => $data['applicant']['exhibitor_administrator']['exhibitor_id'],
            'exhibitor_administrator_id' => $data['applicant']['exhibitor_administrator']['exhibitor_administrator_id'],
            'exhibitor_administrator_uuid' => $data['applicant']['exhibitor_administrator']['exhibitor_administrator_uuid'],
        ];

        $recipientExhibitorInfo = [
            'exhibitor_id' => $data['recipient']['exhibitor_administrator']['exhibitor_id'],
            'exhibitor_administrator_id' => $data['recipient']['exhibitor_administrator']['exhibitor_administrator_id'],
            'exhibitor_administrator_uuid' => $data['recipient']['exhibitor_administrator']['exhibitor_administrator_uuid'],
        ];

        // Clean up unnecessary data
        $cleanData = $this->cleanWebhookData($data, [
            'module_code',
            'exhibitor_administrator_appointment_schedule_detail.approval_message',
            'exhibitor_administrator_appointment_schedule_detail.cancel_message',
            'exhibitor_administrator_appointment_schedule_detail.user_mail_address',
            'exhibitor_administrator_appointment_schedule_detail.user_message',
            'exhibitor_administrator_appointment_schedule_detail.applicant_email',
            'exhibitor_administrator_appointment_schedule_detail.recipient_email',
            'applicant.exhibitor_administrator',
            'recipient.exhibitor_administrator',
        ]);

        $cleanData['applicant'] = $applicantExhibitorInfo;
        $cleanData['recipient'] = $recipientExhibitorInfo;

        $this->matchingUserRepository->addOrUpdateDataFromWebhook($cleanData);
        $this->bizTalksRepository->addOrUpdateDataFromWebhook($cleanData);
    }

    private function cleanWebhookData(array $data, array $keysToRemove): array
    {
        foreach ($keysToRemove as $key) {
            $keys = explode('.', $key);
            $this->unsetNestedKey($data, $keys);
        }
        return $data;
    }

    private function unsetNestedKey(array &$array, array $keys): void
    {
        $key = array_shift($keys);
        if (empty($keys)) {
            unset($array[$key]);
        } elseif (isset($array[$key]) && is_array($array[$key])) {
            $this->unsetNestedKey($array[$key], $keys);
        }
    }
}
