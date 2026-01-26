<?php

namespace App\Services;

use App\Models\CheckinHistory;
use App\Models\LiveChatProfiles;
use App\Models\LiveChatProfileTag;
use App\Models\LiveChatTagContent;
use Illuminate\Support\Collection;
use JsonException;

class MatchingPartnerService
{
    private const string DISCOVER_NETWORKING = 'NETWORKING';
    private const string DISCOVER_EXHIBITOR  = 'EXHIBITOR';
    private const string DISCOVER_VISITOR    = 'VISITOR';

    /**
     * @throws JsonException
     */
    public function getPartners(array $ctx): array
    {
        $networking = $this->getNetworking($ctx);
        $exhibitors = $this->getRandomExhibitors($ctx);
        $visitors   = $this->getRandomVisitors($ctx);

        // Batch attach tags to all profiles in 3 sections
        $allProfiles = collect()
            ->merge($this->flattenNetworkingProfiles($networking))
            ->merge($exhibitors)
            ->merge($visitors);

        $profilesWithTags = $this->attachTags($allProfiles, $ctx['data_source_id'], $ctx['language_id']);

        // re-hydrate
        $networkingOut = $this->hydrateNetworking($networking, $profilesWithTags);
        $exhibitorsOut = $this->mapProfiles($profilesWithTags->where('section', self::DISCOVER_EXHIBITOR)->values());
        $visitorsOut   = $this->mapProfiles($profilesWithTags->where('section', self::DISCOVER_VISITOR)->values());

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
        $myNames = CheckinHistory::query()
            ->whereNull('deleted_at')
//            ->where('event_id', $ctx['event_id'])
            ->where('user_id', $ctx['user_id'])
            ->whereNotNull('checkin_app_user_name')
            ->where('checkin_app_user_name', '!=', '')
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
            $checkins = CheckinHistory::query()
                ->whereNull('deleted_at')
//                ->where('event_id', $ctx['event_id'])
                ->where('checkin_app_user_name', $name)
                ->where(function ($q) use ($ctx) {
                    $q->where('user_id', '!=', $ctx['user_id']);
                })
                ->limit($ctx['limit_networking_per_name'])
                ->get(['user_id', 'exhibitor_administrator_id', 'checkin_app_user_name']);

            if ($checkins->isEmpty()) {
                continue;
            }

            $userIds = $checkins->pluck('user_id')->filter()->unique()->values();
            $adminIds = $checkins->pluck('exhibitor_administrator_id')->filter()->unique()->values();

            $profiles = LiveChatProfiles::query()
                ->whereNull('deleted_at')
                ->where('live_chat_data_source_id', $ctx['data_source_id'])
                ->where('last_event_id', $ctx['event_id'])
                ->where(function ($q) use ($userIds, $adminIds) {
                    if ($userIds->isNotEmpty()) {
                        $q->orWhereIn('user_id', $userIds);
                    }
                    if ($adminIds->isNotEmpty()) {
                        $q->orWhereIn('exhibitor_administrator_id', $adminIds);
                    }
                })
                ->get([
                    'id',
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

    private function getRandomExhibitors(array $ctx): Collection
    {
        return LiveChatProfiles::query()
            ->whereNull('deleted_at')
            ->where('live_chat_data_source_id', $ctx['data_source_id'])
            ->where('last_event_id', $ctx['event_id'])
            ->whereNotNull('exhibitor_administrator_id')
            ->inRandomOrder()
            ->limit($ctx['limit_exhibitors'])
            ->get([
                'id',
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
        return LiveChatProfiles::query()
            ->whereNull('deleted_at')
            ->where('live_chat_data_source_id', $ctx['data_source_id'])
            ->where('last_event_id', $ctx['event_id'])
            ->whereNotNull('user_id')
            ->whereNull('exhibitor_administrator_id')
            ->inRandomOrder()
            ->limit($ctx['limit_visitors'])
            ->get([
                'id',
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

    /**
     * @throws JsonException
     */
    private function attachTags(Collection $profiles, int $dataSourceId, int $languageId): Collection
    {
        if ($profiles->isEmpty()) {
            return $profiles;
        }

        $profileIds = $profiles->pluck('id')->unique()->values();

        // live_chat_profile_tags.tags = JSON array of tag IDs
        $profileTagRows = LiveChatProfileTag::query()
            ->whereNull('deleted_at')
            ->where('live_chat_data_source_id', $dataSourceId)
            ->whereIn('user_live_chat_profile_id', $profileIds)
            ->get(['user_live_chat_profile_id', 'tags']);

        $profileToTagIds = [];
        $allTagIds = collect();

        foreach ($profileTagRows as $row) {
            $tagIds = is_array($row->tags) ? $row->tags : (json_decode($row->tags, true, 512, JSON_THROW_ON_ERROR) ?? []);
            $tagIds = collect($tagIds)->filter(fn($v) => is_numeric($v))->map(fn($v) => (int) $v)->values();
            $profileToTagIds[(int) $row->user_live_chat_profile_id] = $tagIds->all();
            $allTagIds = $allTagIds->merge($tagIds);
        }

        $allTagIds = $allTagIds->unique()->values();

        $tagContents = $allTagIds->isEmpty()
            ? collect()
            : LiveChatTagContent::query()
                ->whereNull('deleted_at')
                ->whereIn('live_chat_tag_id', $allTagIds)
                ->where('language_id', $languageId)
                ->where('is_publish', 1)
                ->get(['live_chat_tag_id', 'name'])
                ->keyBy('live_chat_tag_id');

        return $profiles->map(function ($p) use ($profileToTagIds, $tagContents) {
            $tagIds = $profileToTagIds[(int) $p->id] ?? [];
            $p->tags = collect($tagIds)
                ->map(function (int $tagId) use ($tagContents) {
                    $c = $tagContents->get($tagId);
                    if (!$c) return null;
                    return ['id' => $tagId, 'name' => (string) $c->name];
                })
                ->filter()
                ->values()
                ->all();

            return $p;
        });
    }

    private function mapProfiles(Collection $profiles): array
    {
        return $profiles->map(function ($p) {
            return [
                'live_chat_data_source_id' => (int) $p->live_chat_data_source_id,
                'live_chat_user_id' => (string) $p->live_chat_user_id,
                'profile_id' => (int) $p->id,
                'uuid' => (string) $p->uuid,
                'nickname' => (string) $p->nickname,
                'company' => $p->company !== null ? (string) $p->company : null,
                'introduction' => $p->introduction !== null ? (string) $p->introduction : null,
                'icon_image' => $p->icon_image !== null ? (string) $p->icon_image : null,
                'background_image' => $p->background_image !== null ? (string) $p->background_image : null,
                'user_id' => $p->user_id !== null ? (int) $p->user_id : null,
                'exhibitor_administrator_id' => $p->exhibitor_administrator_id !== null ? (int) $p->exhibitor_administrator_id : null,
                'tags' => $p->tags ?? [],
            ];
        })->values()->all();
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
                'checkin_app_user_name' => (string) $g['checkin_app_user_name'],
                'items' => $this->mapProfiles($items),
            ];
        }

        return [
            'discover_type' => self::DISCOVER_NETWORKING,
            'list' => $outList,
        ];
    }
}
