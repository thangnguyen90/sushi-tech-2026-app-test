<?php

namespace App\Services;

use App\Models\CheckinHistory;
use App\Models\LiveChatProfiles;
use App\Models\LiveChatProfileTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use JsonException;

class MatchingPartnerService
{
    private const string DISCOVER_NETWORKING = 'NETWORKING';
    private const string DISCOVER_EXHIBITOR = 'EXHIBITOR';
    private const string DISCOVER_VISITOR = 'VISITOR';
    private const string TYPE_EXHIBITOR = 'exhibitor';
    private const string TYPE_VISITOR = 'visitor';
    private const int MAX_TYPED_RESULTS = 120;

    /**
     * @throws JsonException
     */
    public function getPartners(array $ctx): array
    {
        if ($this->isPartnerTypeFilterEnabled($ctx)) {
            return $this->getPaginatedPartnersByType($ctx);
        }

        $networking = $this->getNetworking($ctx);
        $exhibitors = $this->getRandomExhibitors($ctx);
        $visitors = $this->getRandomVisitors($ctx);


        return array_values([
            [
                "discover_type" => self::DISCOVER_NETWORKING,
                "list" => $networking
            ],
            [
                'discover_type' => self::DISCOVER_EXHIBITOR,
                'items' => $exhibitors,
            ],
            [
                'discover_type' => self::DISCOVER_VISITOR,
                'items' => $visitors,
            ],
        ]);
    }

    private function getPaginatedPartnersByType(array $ctx): array
    {
        $isExhibitor = $this->resolveIsExhibitorType($ctx);
        $page = max((int) ($ctx['page'] ?? 1), 1);
        $perPage = max((int) ($ctx['per_page'] ?? 1), 1);
        $seed = $this->resolveSeed($ctx);
        $seedNumber = $this->resolveSeedNumber($seed);

        $orderedIds = $this->getTypedPartnerQuery($ctx, $isExhibitor)
            ->distinct()
            ->orderByRaw('(live_chat_profiles.id * ? + ?) % 2147483647', [$seedNumber, $seedNumber % 97])
            ->orderBy('live_chat_profiles.id')
            ->limit(self::MAX_TYPED_RESULTS)
            ->pluck('live_chat_profiles.id')
            ->values();

        $total = $orderedIds->count();
        $offset = ($page - 1) * $perPage;
        $pageIds = $orderedIds->slice($offset, $perPage)->values();

        $items = collect();
        if ($pageIds->isNotEmpty()) {
            $profiles = LiveChatProfiles::query()
                ->whereIn('id', $pageIds->all())
                ->get($this->partnerSelectColumns())
                ->keyBy('id');

            $items = $pageIds->map(function ($id) use ($profiles) {
                return $profiles->get($id);
            })->filter()->values();
        }

        $items = $this->attachTags(
            $items,
            $ctx['data_source_id'] ?? null,
            $ctx['language_id'] ?? 1
        );
        $items = $this->hydrateInformationField($items);
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);

        return [
            'discover_type' => $isExhibitor ? self::DISCOVER_EXHIBITOR : self::DISCOVER_VISITOR,
            'seed' => $seed,
            'items' => $items->values()->all(),
            'total' => $total,
            'paging' => $this->buildPagingData($paginator),
        ];
    }

    private function resolveSeed(array $ctx): string
    {
        $seed = trim((string) ($ctx['seed'] ?? ''));

        if ($seed === '') {
            return '1';
        }

        return $seed;
    }

    private function resolveSeedNumber(string $seed): int
    {
        $seedNumber = abs(crc32($seed));

        return $seedNumber === 0 ? 1 : $seedNumber;
    }

    private function getTypedPartnerQuery(array $ctx, bool $isExhibitor): Builder
    {
        $query = LiveChatProfiles::query()
            ->whereNull('deleted_at')
            ->where('live_chat_data_source_id', $ctx['data_source_id'])
            ->where('last_event_id', $ctx['event_id'])
            ->where('profile_id', '<>', $ctx['profile_id'])
            ->where('is_exhibitor', $isExhibitor);

        if (!empty($ctx['keyword'])) {
            $keyword = $ctx['keyword'];

            $query->where(function ($q) use ($keyword) {
                $q->where('nickname', 'like', '%' . $keyword . '%')
                    ->orWhere('company', 'like', '%' . $keyword . '%');
            });
        }

        $this->applyOptionValueFilter($query, $ctx['option_values'] ?? []);
        $this->removeUserTalked($query, $ctx);

        return $query;
    }

    private function partnerSelectColumns(): array
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
            'custom_fields'
        ];
    }

    private function buildPagingData(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total_pages' => $paginator->lastPage(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }

    private function isPartnerTypeFilterEnabled(array $ctx): bool
    {
        return in_array($ctx['type'] ?? null, [self::TYPE_EXHIBITOR, self::TYPE_VISITOR], true);
    }

    private function resolveIsExhibitorType(array $ctx): bool
    {
        return ($ctx['type'] ?? null) === self::TYPE_EXHIBITOR;
    }

    private function getNetworking(array $ctx): array
    {
        $currentActorId = $this->resolveCurrentActorId($ctx);
        if (!$currentActorId) {
            return [];
        }

        $query = CheckinHistory::query()
            ->limit(config('constants.NET_WORKING_LIMIT') ?? 5)
            ->where('user_id', $currentActorId);

        $myNames = $query
            ->distinct()
            ->pluck('checkin_app_user_name')
            ->values();

        if ($myNames->isEmpty()) {
            return [];
        }

        $groups = [];

        foreach ($myNames as $name) {

            $qUser = CheckinHistory::query()
                ->whereNotNull('checkin_histories.user_id')
                ->where('checkin_app_user_name', $name)
                ->join(
                    'live_chat_profiles',
                    'checkin_histories.user_id',
                    '=',
                    'live_chat_profiles.user_id'
                );

            $qUser->where('checkin_histories.user_id', '<>', $currentActorId);

            if (!empty($ctx['keyword'])) {
                $keyword = $ctx['keyword'];
                $qUser->where(function ($sub) use ($keyword) {
                    $sub->where('nickname', 'like', "%{$keyword}%")
                        ->orWhere('company', 'like', "%{$keyword}%");
                });
            }

            $this->applyOptionValueFilter($qUser, $ctx['option_values'] ?? []);
            $this->removeUserTalked($qUser, $ctx);

            $checkins = $qUser
                ->select($this->baseLiveChatSelect())
                ->limit($ctx['limit_networking_per_name'])
                ->get();

            if ($checkins->isEmpty()) {
                continue;
            }

            // Decode JSON fields manually since unionAll returns stdClass objects
            $checkins = $checkins->map(function ($item) {
                if (is_string($item->icon_image ?? null)) {
                    $decoded = json_decode($item->icon_image, true);
                    $item->icon_image = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
                }
                if (is_string($item->background_image ?? null)) {
                    $decoded = json_decode($item->background_image, true);
                    $item->background_image = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
                }
                if (is_string($item->custom_fields ?? null)) {
                    $decoded = json_decode($item->custom_fields, true);
                    $item->custom_fields = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
                } elseif (!is_array($item->custom_fields ?? null)) {
                    $item->custom_fields = [];
                }
                return $item;
            });

            $checkins = $this->hydrateInformationField($checkins);

            $checkins = $this->attachTags(
                $checkins,
                $ctx['data_source_id'] ?? null,
                $ctx['language_id'] ?? 1
            );

            $groups[] = [
                'checkin_app_user_name' => $name,
                'items' => $checkins,
            ];
        }

        return $groups;
    }

    /**
     * Filter profiles by selected option_value in live_chat_profile_field_options.
     * UI sends max 1 value but we accept array for compatibility.
     */
    private function applyOptionValueFilter(Builder $query, array $optionValues): void
    {
        $vals = array_values(array_unique(array_filter(array_map('strval', $optionValues))));
        if (empty($vals)) {
            return;
        }

        $requiredCount = count($vals);

        $query->whereExists(function ($sub) use ($vals, $requiredCount) {
            $sub->selectRaw('1')
                ->from('live_chat_profile_field_options as fo')
                ->whereNull('fo.deleted_at')
                ->whereColumn('fo.profile_id', 'live_chat_profiles.profile_id')
                ->whereIn('fo.option_value', $vals)
                ->groupBy('fo.profile_id')
                ->havingRaw('COUNT(DISTINCT fo.option_value) = ?', [$requiredCount]);
        });
    }

    private function removeUserTalked(Builder $query, array $ctx): void
    {
        $currentId = $this->resolveCurrentActorId($ctx);
        if (!$currentId) {
            return;
        }

        $query->whereNotExists(function ($sub) use ($currentId) {
            $sub->selectRaw('1')
                ->from('matching_users as mu')
                ->whereNull('mu.deleted_at')
                ->where(function ($q) use ($currentId) {
                    // (current, candidate)
                    $q->where(function ($qq) use ($currentId) {
                        $qq->where('mu.owner_user_id', $currentId)
                            ->whereColumn('mu.peer_user_id', 'live_chat_profiles.user_id');
                    })
                        // OR (candidate, current)
                        ->orWhere(function ($qq) use ($currentId) {
                            $qq->whereColumn('mu.owner_user_id', 'live_chat_profiles.user_id')
                                ->where('mu.peer_user_id', $currentId);
                        });
                });
        });
    }

    private function attachTags(Collection $profiles, ?int $dataSourceId, int $languageId)
    {
        $profileIds = $profiles->pluck('profile_id')->unique()->values();
        // live_chat_profile_tags.tags = JSON array of tag IDs
        $profileTagRows = LiveChatProfileTag::query()
            ->where('live_chat_data_source_id', $dataSourceId)
            ->whereIn('user_live_chat_profile_id', $profileIds)
            ->get()->pluck('tags', 'user_live_chat_profile_id');
        foreach ($profiles as &$row) {
            $tags = $profileTagRows[$row->profile_id] ?? [];
            if (empty($tags)) {
                $row->tags = [];
                continue;
            }
            $row->tags = $tags[$languageId] ?? [];
        }
        unset($row);
        return $profiles;
    }

    private function getRandomExhibitors(array $ctx): Collection
    {
        $query = LiveChatProfiles::query()
            ->whereNull('deleted_at')
            ->where('live_chat_data_source_id', $ctx['data_source_id'])
            ->where('last_event_id', $ctx['event_id'])
            ->where('profile_id', '<>', $ctx['profile_id'])
            ->where('is_exhibitor', true);
        if (!empty($ctx['keyword'])) {
            $keyword = $ctx['keyword'];

            $query->where(function ($q) use ($keyword) {
                $q->where('nickname', 'like', '%' . $keyword . '%')
                    ->orWhere('company', 'like', '%' . $keyword . '%');
            });
        }
        $this->applyOptionValueFilter($query, $ctx['option_values'] ?? []);
        $this->removeUserTalked($query, $ctx);
        $results = $query->distinct()->inRandomOrder()
            ->limit($ctx['limit_exhibitors'])
            ->get([
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
                'custom_fields'
            ]);
        $results = $this->attachTags($results, $ctx['data_source_id'] ?? null, $ctx['language_id'] ?? 1);
        return $this->hydrateInformationField($results);
    }

    private function getRandomVisitors(array $ctx): Collection
    {
        $query = LiveChatProfiles::query()
            ->whereNull('deleted_at')
            ->where('live_chat_data_source_id', $ctx['data_source_id'])
            ->where('last_event_id', $ctx['event_id'])
            ->where('profile_id', '<>', $ctx['profile_id'])
            ->where('is_exhibitor', false);
        if (!empty($ctx['keyword'])) {
            $keyword = $ctx['keyword'];

            $query->where(function ($q) use ($keyword) {
                $q->where('nickname', 'like', '%' . $keyword . '%')
                    ->orWhere('company', 'like', '%' . $keyword . '%');
            });
        }
        $this->applyOptionValueFilter($query, $ctx['option_values'] ?? []);
        $this->removeUserTalked($query, $ctx);
        $results = $query->distinct()->inRandomOrder()
            ->limit($ctx['limit_visitors'])
            ->get([
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
                'custom_fields'
            ]);
        $results = $this->attachTags($results, $ctx['data_source_id'] ?? null, $ctx['language_id'] ?? 1);
        return $this->hydrateInformationField($results);
    }

    private function baseLiveChatSelect()
    {
        return [
            'live_chat_profiles.live_chat_data_source_id',
            'live_chat_profiles.live_chat_user_id',
            'live_chat_profiles.profile_id',
            'live_chat_profiles.uuid',
            'live_chat_profiles.nickname',
            'live_chat_profiles.company',
            'live_chat_profiles.introduction',
            'live_chat_profiles.icon_image',
            'live_chat_profiles.background_image',
            'live_chat_profiles.user_id',
            'live_chat_profiles.is_exhibitor',
            'live_chat_profiles.custom_fields'
        ];
    }

    private function resolveCurrentActorId(array $ctx): ?int
    {
        $userId = $ctx['user_id'] ?? null;
        if (is_numeric($userId) && (int) $userId > 0) {
            return (int) $userId;
        }

        return null;
    }

    private function hydrateInformationField(Collection $profiles): Collection
    {
        return $profiles->map(function ($row) {
            $customFields = $row->custom_fields ?? null;

            if (is_string($customFields)) {
                $decoded = json_decode($customFields, true);
                $customFields = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
            }

            if (!is_array($customFields)) {
                $customFields = [];
            }

            $value = $customFields[config('constants.CHAT_PROFILE_INFORMATION')] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            $row->introduction = $value;
//            $row->custom_fields = $customFields;
            return $row;
        });
    }
}
