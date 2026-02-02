<?php

namespace App\Services;

use App\Models\LiveChatProfiles;
use App\Models\LiveChatProfileTag;
use App\Models\LiveChatTagContent;
use App\Repositories\LiveChatProfileFieldOptionRepository;
use App\Repositories\ChatProfileContentRepository;
use JsonException;

class LiveChatProfileDetailsService
{
    public function __construct(
        private readonly ChatProfileContentRepository $chatProfileContentRepository,
        private readonly LiveChatProfileFieldOptionRepository $liveChatProfileFieldOptionRepository
    ) {}

    public function getDetail(array $ctx): array
    {
        $profile = $this->getProfile($ctx);
        if (!$profile) {
            return [];
        }

        $languageId =  config("language.{$ctx['lang']}", 1);
        $profile = $this->attachTagsOne($profile, $languageId);
        $customFields = $this->liveChatProfileFieldOptionRepository
            ->getResolvedCustomFields((int) $profile->profile_id, (string) $ctx['lang']);

        return [
            'live_chat_data_source_id' => (int) $profile->live_chat_data_source_id,
            'live_chat_user_id' => (string) $profile->live_chat_user_id,
            'profile_id' => (int) $profile->id,
            'uuid' => (string) $profile->uuid,
            'nickname' => (string) $profile->nickname,
            'company' => $profile->company !== null ? (string) $profile->company : null,
            'introduction' => $profile->introduction !== null ? (string) $profile->introduction : null,
            'icon_image' => $profile->icon_image !== null ? (string) $profile->icon_image : null,
            'background_image' => $profile->background_image !== null ? (string) $profile->background_image : null,
            'user_id' => $profile->user_id !== null ? (int) $profile->user_id : null,
            'exhibitor_administrator_id' => $profile->exhibitor_administrator_id !== null ? (int) $profile->exhibitor_administrator_id : null,
            'tags' => $profile->tags ?? [],
            'custom_fields' => $customFields,
        ];
    }

    private function getProfile(array $ctx): ?LiveChatProfiles
    {
        return LiveChatProfiles::query()
            ->whereNull('deleted_at')
            ->where('profile_id', (int) $ctx['profile_id'])
            ->first([
                'id',
                'profile_id',
                'live_chat_data_source_id',
                'live_chat_user_id',
                'uuid',
                'nickname',
                'company',
                'introduction',
                'icon_image',
                'background_image',
                'user_id',
                'exhibitor_administrator_id',
                'custom_fields', // ensure cast to array in model
            ]);
    }

    /**
     * Attach tags for a single profile.
     *
     * @throws JsonException
     */
    private function attachTagsOne(LiveChatProfiles $profile, int $languageId): LiveChatProfiles
    {
        $row = LiveChatProfileTag::query()
            ->whereNull('deleted_at')
            ->where('user_live_chat_profile_id', (int) $profile->profile_id)
            ->first(['tags']);
        if (!$row) {
            $profile->tags = [];
            return $profile;
        }
        $profile->tags = $row->tags[$languageId];

        return $profile;
    }

    private function normalizeLang(string $lang): string
    {
        $l = strtolower(trim($lang));
        return $l === 'eng' ? 'eng' : 'jpn';
    }
}
