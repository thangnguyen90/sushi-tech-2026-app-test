<?php

namespace App\Services;

use App\Models\CheckinHistory;
use App\Models\LiveChatProfiles;
use App\Models\LiveChatProfileTag;
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


        return array_values([
            [
                "discover_type" => "NETWORKING",
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

    private function getNetworking(array $ctx): array
    {
        $query = CheckinHistory::query()->limit(config('constants.NET_WORKING_LIMIT') ?? 5);
        if ($ctx['user_id']) {
            $query->where('user_id', $ctx['user_id']);
        } elseif ($ctx['exhibitor_administrator_id']) {
            $query->where('exhibitor_administrator_id', $ctx['exhibitor_administrator_id']);
        } else {
            return [];
        }

        $myNames = $query
            ->distinct()
            ->pluck('checkin_app_user_name')
            ->values();
        if ($myNames->isEmpty()) {
            return [];
        }

        $groups = [];
        foreach ($myNames as $name) {
            $query = CheckinHistory::query()
                ->where('checkin_app_user_name', $name)
                ->limit($ctx['limit_networking_per_name']);
            $checkinsTableName = (new CheckinHistory)->getTable();
            if ($ctx['user_id']) {
                $query->where($checkinsTableName . '.user_id', '<>', $ctx['user_id']);
            }
            if ($ctx['exhibitor_administrator_id']) {
                $query->where($checkinsTableName . '.exhibitor_administrator_id', '<>', $ctx['exhibitor_administrator_id']);
            }
            if (!$query->exists()) {
                continue;
            }

            $query->join('live_chat_profiles', function ($join) {
                $join->on('checkin_histories.user_id', '=', 'live_chat_profiles.user_id')
                    ->orOn('checkin_histories.exhibitor_administrator_id', '=', 'live_chat_profiles.exhibitor_administrator_id');
            });
            if (!empty($ctx['keyword'])) {
                $keyword = $ctx['keyword'];

                $query->where(function ($q) use ($keyword) {
                    $q->where('nickname', 'like', '%' . $keyword . '%')
                        ->orWhere('company', 'like', '%' . $keyword . '%');
                });
            }
            $this->applyOptionValueFilter($query, $ctx['option_values'] ?? []);
            $this->removeUserTalked($query, $ctx);
            $checkins = $query->select(
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
                'live_chat_profiles.exhibitor_administrator_id'
            )
                ->get();
            $checkins = $this->attachTags($checkins, $ctx['data_source_id'] ?? null, $ctx['language_id'] ?? 1);
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

        $query->whereExists(function ($sub) use ($vals) {
            $sub->selectRaw('1')
                ->from('live_chat_profile_field_options as fo')
                ->whereNull('fo.deleted_at')
                ->whereColumn('fo.profile_id', 'live_chat_profiles.profile_id')
                ->whereIn('fo.option_value', $vals);
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
            ->where('live_chat_data_source_id', $ctx['data_source_id'])
            ->where('last_event_id', $ctx['event_id'])
            ->where('profile_id', '<>', $ctx['profile_id'])
            ->whereNull('user_id'); // exhibitor
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
                'exhibitor_administrator_id',
            ]);
        return $this->attachTags($results, $ctx['data_source_id'] ?? null, $ctx['language_id'] ?? 1);
    }

    private function getRandomVisitors(array $ctx): Collection
    {
        $query = LiveChatProfiles::query()
            ->whereNull('deleted_at')
            ->where('live_chat_data_source_id', $ctx['data_source_id'])
            ->where('last_event_id', $ctx['event_id'])
            ->where('profile_id', '<>', $ctx['profile_id'])
            ->whereNotNull('user_id');
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
                'exhibitor_administrator_id',
            ]);
        return $this->attachTags($results, $ctx['data_source_id'] ?? null, $ctx['language_id'] ?? 1);
    }

    private function removeUserTalked(Builder $query, array $ctx): void
    {
        $query->whereNotExists(function ($sub) use ($ctx) {
            $sub->selectRaw('1')
                ->from('matching_users as mu')
                ->whereNull('mu.deleted_at')

            ;
            if(!empty($ctx['user_uuid'])) {
                $sub->where(function ($q) use ($ctx) {
                    $q->whereColumn('mu.owner_user_id', 'live_chat_profiles.exhibitor_administrator_id')
                        ->orWhereColumn('mu.peer_user_id', 'live_chat_profiles.exhibitor_administrator_id');
                })->where(function ($q) use ($ctx) {
                    $q->where('mu.owner_user_id', $ctx['exhibitor_administrator_id'])
                        ->where('mu.peer_user_id', $ctx['exhibitor_administrator_id']);
                    });
            } else {
                $sub->where(function ($q){
                    $q->whereColumn('mu.owner_user_id', 'live_chat_profiles.user_id')
                        ->orWhereColumn('mu.peer_user_id', 'live_chat_profiles.user_id');
                })->where(function ($q) use ($ctx) {
                    $q->where('mu.owner_user_id', $ctx['user_id'])
                        ->where('mu.peer_user_id', $ctx['user_id']);
                });
            }
        });
    }
}
