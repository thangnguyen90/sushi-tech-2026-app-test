<?php

namespace App\Services;

use App\Models\LiveChatProfiles;
use App\Models\LiveChatProfileTag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiRecommendService
{
    public function buildRecommendResult(string $userUuid, string $content): array
    {
        $recommendResponse = $this->fetchThirdPartyRecommend($userUuid, $content)
            ?? $this->buildEmptyRecommendResponse();
        $profiles = $this->getProfilesByUuids($recommendResponse['user_uuid_list']);

        return [
            'user_uuid_list' => $profiles->pluck('uuid')->values()->all(),
            'reason' => $recommendResponse['reason'],
            'items' => $profiles->values()->toArray(),
        ];
    }

    private function fetchThirdPartyRecommend(string $userUuid, string $content): ?array
    {
        $apiUrl = $this->resolveApiUrl();
        if ($apiUrl === '') {
            return null;
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        try {
            $response = Http::timeout((int) config('services.ai_recommend.timeout', 10))
                ->withHeaders($headers)
                ->post($apiUrl, [
                    'user_uuid' => $userUuid,
                    'content' => $content,
                ]);

            if (! $response->successful()) {
                Log::warning('AI recommend API request failed.', [
                    'status' => $response->status(),
                    'url' => $apiUrl,
                ]);

                return null;
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return null;
            }

            $result = $payload['result'] ?? $payload;
            if (! is_array($result)) {
                return null;
            }

            $uuidList = array_values(array_filter(
                $result['user_uuid_list'] ?? [],
                static fn (mixed $uuid): bool => is_string($uuid) && $uuid !== ''
            ));

            if (empty($uuidList)) {
                return null;
            }

            $reason = $result['reason'] ?? '';

            return [
                'user_uuid_list' => $uuidList,
                'reason' => is_string($reason) ? $reason : '',
            ];
        } catch (Throwable $e) {
            Log::warning('AI recommend API request exception.', [
                'url' => $apiUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveApiUrl(): string
    {
        return trim((string) config('services.ai_recommend.url', ''));
    }

    private function buildEmptyRecommendResponse(): array
    {
        return [
            'user_uuid_list' => [],
            'reason' => '',
        ];
    }

    private function getProfilesByUuids(array $userUuids): Collection
    {
        if (empty($userUuids)) {
            return collect();
        }

        $profiles = LiveChatProfiles::query()
            ->whereNull('deleted_at')
            ->whereIn('uuid', $userUuids)
            ->get($this->profileColumns());

        if ($profiles->isEmpty()) {
            return $profiles;
        }

        $sortedProfiles = $profiles->sortBy(
            static fn (LiveChatProfiles $profile): int => array_search($profile->uuid, $userUuids, true)
        )->values();

        $sortedProfiles = $this->attachTags($sortedProfiles);

        return $this->hydrateInformationField($sortedProfiles);
    }

    private function attachTags(Collection $profiles): Collection
    {
        $profileIds = $profiles->pluck('profile_id')->unique()->values();
        if ($profileIds->isEmpty()) {
            return $profiles;
        }

        $tagRows = LiveChatProfileTag::query()
            ->whereIn('user_live_chat_profile_id', $profileIds)
            ->get()
            ->pluck('tags', 'user_live_chat_profile_id');

        foreach ($profiles as $profile) {
            $tags = $tagRows[$profile->profile_id] ?? [];

            if (empty($tags) || ! is_array($tags)) {
                $profile->tags = [];

                continue;
            }

            $profile->tags = $tags[1] ?? [];
        }

        return $profiles;
    }

    private function hydrateInformationField(Collection $profiles): Collection
    {
        return $profiles->map(function (LiveChatProfiles $profile): LiveChatProfiles {
            $customFields = $profile->custom_fields ?? null;

            if (is_string($customFields)) {
                $decoded = json_decode($customFields, true);
                $customFields = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
            }

            if (! is_array($customFields)) {
                $customFields = [];
            }

            $value = $customFields[config('constants.CHAT_PROFILE_INFORMATION')] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            $profile->introduction = $value;

            return $profile;
        });
    }

    private function profileColumns(): array
    {
        return [
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
            'is_exhibitor',
            'custom_fields',
        ];
    }
}
