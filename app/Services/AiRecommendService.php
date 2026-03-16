<?php

namespace App\Services;

use App\Models\LiveChatProfiles;
use App\Models\LiveChatProfileTag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AiRecommendService
{
    private const array MOCK_RECOMMENDED_USER_UUIDS = [
        '336bef36-0e67-48c1-9b8d-3c591dcd6876',
        '247a364a-2eb8-472c-96b0-29e906e497c9',
        '9b86fd81-427b-4bbf-aa6b-c17d47c727c8',
    ];

    public function buildRecommendResult(string $userUuid, string $content, array $overrideUserUuidList = []): array
    {
        $overrideUuids = $this->normalizeOverrideUserUuids($overrideUserUuidList, $userUuid);
        $recommendResponse = !empty($overrideUuids)
            ? $this->buildOverrideMockResponse($overrideUuids, $content)
            : ($this->fetchThirdPartyRecommend($userUuid, $content)
                ?? $this->buildMockAiResponse($userUuid, $content));
        $profiles = $this->getProfilesByUuids($recommendResponse['user_uuid_list']);

        if ($profiles->isEmpty()) {
            $profiles = $this->getFallbackProfiles($userUuid);
        }

        return [
            'user_uuid_list' => $profiles->pluck('uuid')->values()->all(),
            'reason' => $recommendResponse['reason'],
            'items' => $profiles->values()->toArray(),
        ];
    }

    private function normalizeOverrideUserUuids(array $userUuids, string $excludeUserUuid): array
    {
        $normalized = array_values(array_unique(array_filter(
            $userUuids,
            static fn (mixed $uuid): bool => is_string($uuid) && Str::isUuid($uuid) && $uuid !== $excludeUserUuid
        )));

        return $normalized;
    }

    private function buildOverrideMockResponse(array $overrideUuids, string $content): array
    {
        $trimmedContent = Str::of($content)->trim()->limit(80, '...');

        return [
            'user_uuid_list' => $overrideUuids,
            'reason' => sprintf(
                'Mock AI suggest users from request override related to: %s',
                $trimmedContent
            ),
        ];
    }

    private function fetchThirdPartyRecommend(string $userUuid, string $content): ?array
    {
        $apiUrl = trim((string) config('services.ai_recommend.url', ''));
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

            if (!$response->successful()) {
                Log::warning('AI recommend API request failed.', [
                    'status' => $response->status(),
                    'url' => $apiUrl,
                ]);

                return null;
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                return null;
            }

            $result = $payload['result'] ?? $payload;
            if (!is_array($result)) {
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

    private function buildMockAiResponse(string $userUuid, string $content): array
    {
        $recommendedUuids = array_values(array_filter(
            self::MOCK_RECOMMENDED_USER_UUIDS,
            static fn (string $uuid): bool => $uuid !== $userUuid
        ));

        $trimmedContent = Str::of($content)->trim()->limit(80, '...');

        return [
            'user_uuid_list' => $recommendedUuids,
            'reason' => sprintf(
                'Mock AI suggest users with similar interests related to: %s',
                $trimmedContent
            ),
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

    private function getFallbackProfiles(string $excludeUserUuid): Collection
    {
        $profiles = LiveChatProfiles::query()
            ->whereNull('deleted_at')
            ->whereNotNull('uuid')
            ->where('uuid', '<>', $excludeUserUuid)
            ->limit(3)
            ->get($this->profileColumns());

        if ($profiles->isEmpty()) {
            return $profiles;
        }

        $profiles = $this->attachTags($profiles->values());

        return $this->hydrateInformationField($profiles);
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
