<?php

namespace App\Services;

use App\Models\CheckinHistory;
use App\Models\LiveChatProfiles;
use App\Models\LiveChatProfileTag;
use App\Models\LiveChatTagContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use JsonException;

class MatchingPartnerService
{
    private const string DISCOVER_NETWORKING = 'NETWORKING';
    private const string DISCOVER_EXHIBITOR = 'EXHIBITOR';
    private const string DISCOVER_VISITOR = 'VISITOR';

    /**
     * @throws JsonException
     */
    public function getPartners(array $ctx): array
    {
        $networking = $this->getNetworking($ctx);
        $exhibitors = $this->getRandomExhibitors($ctx);
        $visitors = $this->getRandomVisitors($ctx);

        // Batch attach tags to all profiles in 3 sections
        $allProfiles = collect()
            ->merge($this->flattenNetworkingProfiles($networking))
            ->merge($exhibitors)
            ->merge($visitors);
        $profilesWithTags = $this->attachTags($allProfiles, $ctx['data_source_id'], $ctx['language_id']);

        // re-hydrate
        $networkingOut = $this->hydrateNetworking($networking, $profilesWithTags);
        $exhibitorsOut = $this->mapProfiles($profilesWithTags->where('section', self::DISCOVER_EXHIBITOR)->values());
        $visitorsOut = $this->mapProfiles($profilesWithTags->where('section', self::DISCOVER_VISITOR)->values());

        return array_values(array_filter([
            $networkingOut,
            [
                'discover_type' => self::DISCOVER_EXHIBITOR,
                'items' => $exhibitorsOut,
            ],
            [
                'discover_type' => self::DISCOVER_VISITOR,
                'items' => $visitorsOut,
            ],
        ]));
    }

    private function getNetworking(array $ctx): array
    {
        $query = CheckinHistory::query()->limit(config('constants.NET_WORKING_LIMIT') ?? 5);
        if ($ctx['user_id']) {
            $query->where('user_id', $ctx['user_id']);
        } elseif ($ctx['exhibitor_administrator_id']) {
            $query->where('exhibitor_administrator_id', $ctx['exhibitor_administrator_id']);
        } else {
            return [
                'discover_type' => self::DISCOVER_NETWORKING,
                'list' => [],
            ];
        }


        $myNames = $query
            ->distinct()
            ->pluck('checkin_app_user_name')
            ->values();
        if ($myNames->isEmpty()) {
            return [
                'discover_type' => self::DISCOVER_NETWORKING,
                'list' => [],
            ];
        }

        $groups = [];
        foreach ($myNames as $name) {
            $query = CheckinHistory::query()
                ->where('checkin_app_user_name', $name)
                ->where('user_id', '<>', $ctx['user_id'])
                ->limit($ctx['limit_networking_per_name']);

            if (!$query->exists()) {
                continue;
            }

            $checkins = $query->get(['user_id', 'exhibitor_administrator_id', 'checkin_app_user_name']);


            $userIds = $checkins->pluck('user_id')->filter()->unique()->values();
            $adminIds = $checkins->pluck('exhibitor_administrator_id')->filter()->unique()->values();

            $profilesQuery = LiveChatProfiles::query()
                ->where('live_chat_data_source_id', $ctx['data_source_id'])
                ->where('last_event_id', $ctx['event_id'])
                ->where('user_id', '<>', $ctx['user_id'])
                ->where(function ($q) use ($userIds, $adminIds) {
                    if ($userIds->isNotEmpty()) {
                        $q->orWhereIn('user_id', $userIds);
                    }
                    if ($adminIds->isNotEmpty()) {
                        $q->orWhereIn('exhibitor_administrator_id', $adminIds);
                    }
                });

            $this->applyOptionValueFilter($profilesQuery, $ctx['option_values'] ?? []);
            $this->applyMatchedExclusion($profilesQuery, $ctx, [
                'live_chat_profiles.user_id',
                'live_chat_profiles.exhibitor_administrator_id',
            ]);

            if (!empty($ctx['keyword'])) {
                $keyword = $ctx['keyword'];
                $profilesQuery->where(function ($q) use ($keyword) {
                    $q->where('nickname', 'like', '%' . $keyword . '%')
                        ->orWhere('company', 'like', '%' . $keyword . '%');
                });
            }

            if (!$profilesQuery->exists()) {
                continue;
            }
            $profiles = $profilesQuery->get([
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
            ])
                ->map(function ($p) {
                    $p->section = self::DISCOVER_NETWORKING;
                    return $p;
                })
                ->values();
            if ($profiles->isEmpty()) {
                continue;
            }

            $groups[] = [
                'checkin_app_user_name' => $name,
                'items' => $profiles,
            ];
        }

        return [
            'discover_type' => self::DISCOVER_NETWORKING,
            'list' => $groups,
        ];
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

        $query->whereExists(function ($sub) use ($vals) {
            $sub->selectRaw('1')
                ->from('live_chat_profile_field_options as fo')
                ->whereNull('fo.deleted_at')
                ->whereColumn('fo.profile_id', 'live_chat_profiles.profile_id')
                ->whereIn('fo.option_value', $vals);
        });
    }

    private function getRandomExhibitors(array $ctx): Collection
    {
        $query = LiveChatProfiles::query()
            ->where('live_chat_data_source_id', $ctx['data_source_id'])
            ->where('last_event_id', $ctx['event_id'])
            ->where('user_id' , '<>', $ctx['user_id'])
            ->whereNotNull('exhibitor_administrator_id');
        if (!empty($ctx['keyword'])) {
            $keyword = $ctx['keyword'];

            $query->where(function ($q) use ($keyword) {
                $q->where('nickname', 'like', '%' . $keyword . '%')
                    ->orWhere('company', 'like', '%' . $keyword . '%');
            });
        }
        $this->applyOptionValueFilter($query, $ctx['option_values'] ?? []);
        return
            $query->distinct()->inRandomOrder()
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
                    'exhibitor_administrator_id',
                ])
                ->map(function ($p) {
                    $p->section = self::DISCOVER_EXHIBITOR;
                    return $p;
                });
    }

    private function getRandomVisitors(array $ctx): Collection
    {
        $query = LiveChatProfiles::query()
            ->whereNull('deleted_at')
            ->where('live_chat_data_source_id', $ctx['data_source_id'])
            ->where('last_event_id', $ctx['event_id'])
            ->whereNotNull('user_id')
            ->where('user_id', '<>', $ctx['user_id'])
            ->whereNull('exhibitor_administrator_id');
        if (!empty($ctx['keyword'])) {
            $keyword = $ctx['keyword'];

            $query->where(function ($q) use ($keyword) {
                $q->where('nickname', 'like', '%' . $keyword . '%')
                    ->orWhere('company', 'like', '%' . $keyword . '%');
            });
        }
        $this->applyOptionValueFilter($query, $ctx['option_values'] ?? []);
        $this->applyMatchedExclusion($query, $ctx, ['live_chat_profiles.user_id']);

        return $query
            ->distinct()
            ->inRandomOrder()
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
                'exhibitor_administrator_id',
            ])
            ->map(function ($p) {
                $p->section = self::DISCOVER_VISITOR;
                return $p;
            });
    }

    private function flattenNetworkingProfiles(array $networking): Collection
    {
        $all = collect();
        foreach (($networking['list'] ?? []) as $g) {
            $items = $g['items'] ?? collect();
            $all = $all->merge($items);
        }
        return $all;
    }

    /**
     * @throws JsonException
     */
    private function attachTags(Collection $profiles, ?int $dataSourceId, int $languageId): Collection
    {
        if ($profiles->isEmpty()) {
            return $profiles;
        }

        $profileIds = $profiles->pluck('profile_id')->unique()->values();
        // live_chat_profile_tags.tags = JSON array of tag IDs
        $profileTagRows = LiveChatProfileTag::query()
            ->where('live_chat_data_source_id', $dataSourceId)
            ->whereIn('user_live_chat_profile_id', $profileIds)
            ->get()->pluck('tags', 'user_live_chat_profile_id');
        foreach ($profiles as &$row) {
            $tags = $profileTagRows[$row->profile_id] ?? [];
            if (empty($tags)) {
                continue;
            }
            $row->tags = $tags[$languageId] ?? [];
        }
        unset($row);
        return $profiles;
    }

    private function hydrateNetworking(array $networking, Collection $profilesWithTags): array
    {
        $byId = $profilesWithTags->keyBy('id');

        $outList = [];
        foreach (($networking['list'] ?? []) as $g) {
            $items = collect($g['items'] ?? [])
                ->map(fn($p) => $byId->get($p->id))
                ->filter()
                ->values();

            $outList[] = [
                'checkin_app_user_name' => (string)$g['checkin_app_user_name'],
                'items' => $this->mapProfiles($items),
            ];
        }

        return [
            'discover_type' => self::DISCOVER_NETWORKING,
            'list' => $outList,
        ];
    }

    private function mapProfiles(Collection $profiles): array
    {
        return $profiles->map(function ($p) {
            return [
                'live_chat_data_source_id' => (int)$p->live_chat_data_source_id,
                'live_chat_user_id' => (string)$p->live_chat_user_id,
                'profile_id' => (int)$p->profile_id,
                'uuid' => (string)$p->uuid,
                'nickname' => (string)$p->nickname,
                'company' => $p->company !== null ? (string)$p->company : null,
                'introduction' => $p->introduction !== null ? (string)$p->introduction : null,
                'user_id' => $p->user_id !== null ? (int)$p->user_id : null,
                'exhibitor_administrator_id' => $p->exhibitor_administrator_id !== null ? (int)$p->exhibitor_administrator_id : null,
                'icon_image' => $p->icon_image,
                'background_image' => $p->background_image,
                'tags' => $p->tags ?? [],
            ];
        })->values()->all();
    }

    /**
     * Exclude profiles that already have a matching row with current user
     * (as owner_user_id OR peer_user_id) in same event.
     *
     * @param array<int, string> $candidateColumns Columns on live_chat_profiles to compare (e.g. user_id, exhibitor_administrator_id)
     * @param array<int, int>|null $excludeStatuses Optional statuses to exclude (null = exclude all statuses)
     */
    private function applyMatchedExclusion(
        Builder $query,
        array $ctx,
        array $candidateColumns,
        ?array $excludeStatuses = null
    ): void {
        $eventId = $ctx['event_id'] ?? null;
        if (!$eventId || empty($candidateColumns)) {
            return;
        }

        $matchingTable = config('constants.MATCHING_PARTNER_TABLE') ?: 'matching_users';
        $candidateColumns = array_values(array_filter(array_unique($candidateColumns)));

        $query->whereNotExists(function ($sub) use ($matchingTable, $eventId, $candidateColumns, $excludeStatuses) {
            $sub->selectRaw('1')
                ->from($matchingTable . ' as mp')
                ->whereNull('mp.deleted_at')
                ->where('mp.event_id', $eventId);

            if (is_array($excludeStatuses) && !empty($excludeStatuses)) {
                $sub->whereIn('mp.status', $excludeStatuses);
            }

            // candidate appears in matching_users as owner OR peer -> exclude
            $sub->where(function ($w) use ($candidateColumns) {
                foreach ($candidateColumns as $i => $col) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $w->{$method}(function ($x) use ($col) {
                        $x->whereColumn('mp.owner_user_id', $col)
                            ->orWhereColumn('mp.peer_user_id', $col);
                    });
                }
            });
        });
    }
}
