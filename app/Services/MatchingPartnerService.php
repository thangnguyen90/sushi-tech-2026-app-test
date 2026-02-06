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

    private function getNetworking(array $ctx): array
    {
        $query = CheckinHistory::query()
            ->limit(config('constants.NET_WORKING_LIMIT') ?? 5);

        if (!empty($ctx['user_id'])) {
            $query->where('user_id', $ctx['user_id']);
        } elseif (!empty($ctx['exhibitor_administrator_id'])) {
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

            /** -------------------------------
             * Query 1: JOIN by user_id
             * -------------------------------- */
            $qUser = CheckinHistory::query()
                ->whereNotNull('checkin_histories.user_id')
                ->where('checkin_app_user_name', $name)
                ->join(
                    'live_chat_profiles',
                    'checkin_histories.user_id',
                    '=',
                    'live_chat_profiles.user_id'
                );

            if (!empty($ctx['user_id'])) {
                $qUser->where('checkin_histories.user_id', '<>', $ctx['user_id']);
            }

            /** -------------------------------
             * Query 2: JOIN by exhibitor_administrator_id
             * -------------------------------- */
            $qExhibitor = CheckinHistory::query()
                ->whereNull('checkin_histories.user_id')
                ->where('checkin_app_user_name', $name)
                ->join(
                    'live_chat_profiles',
                    'checkin_histories.exhibitor_administrator_id',
                    '=',
                    'live_chat_profiles.exhibitor_administrator_id'
                );

            if (!empty($ctx['exhibitor_administrator_id'])) {
                $qExhibitor->where(
                    'checkin_histories.exhibitor_administrator_id',
                    '<>',
                    $ctx['exhibitor_administrator_id']
                );
            }

            /** -------------------------------
             * Apply shared filters
             * -------------------------------- */
            foreach ([$qUser, $qExhibitor] as $q) {

                if (!empty($ctx['keyword'])) {
                    $keyword = $ctx['keyword'];
                    $q->where(function ($sub) use ($keyword) {
                        $sub->where('nickname', 'like', "%{$keyword}%")
                            ->orWhere('company', 'like', "%{$keyword}%");
                    });
                }

                $this->applyOptionValueFilter($q, $ctx['option_values'] ?? []);
                $this->removeUserTalked($q, $ctx);

                $q->select($this->baseLiveChatSelect());
            }

            /** -------------------------------
             * UNION ALL + LIMIT
             * -------------------------------- */
            $checkins = $qUser
                ->unionAll($qExhibitor)
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
        // current user id: ưu tiên exhibitor_administrator_id nếu có user_uuid (theo logic code cũ của bạn)
        $currentId = !empty($ctx['user_uuid'])
            ? (int)($ctx['exhibitor_administrator_id'] ?? 0)
            : (int)($ctx['user_id'] ?? 0);

        if ($currentId <= 0) {
            return; // không có current id hợp lệ thì không áp filter
        }

        $query->whereNotExists(function ($sub) use ($currentId) {
            $candidateIdExpr = "COALESCE(live_chat_profiles.user_id, live_chat_profiles.exhibitor_administrator_id)";

            $sub->selectRaw('1')
                ->from('matching_users as mu')
                ->whereNull('mu.deleted_at')
                ->where(function ($q) use ($currentId, $candidateIdExpr) {
                    // (current, candidate)
                    $q->where(function ($qq) use ($currentId, $candidateIdExpr) {
                        $qq->where('mu.owner_user_id', $currentId)
                            ->whereRaw("mu.peer_user_id = {$candidateIdExpr}");
                    })
                        // OR (candidate, current)
                        ->orWhere(function ($qq) use ($currentId, $candidateIdExpr) {
                            $qq->whereRaw("mu.owner_user_id = {$candidateIdExpr}")
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
                'custom_fields'
            ]);
        $results = $this->attachTags($results, $ctx['data_source_id'] ?? null, $ctx['language_id'] ?? 1);
        return $this->hydrateInformationField($results);
    }

    //    private function removeUserTalked(Builder $query, array $ctx): void
    //    {

    // neu van loi dung thu nay
    //        $targetUserId = $ctx['user_id'] ?? $ctx['exhibitor_administrator_id'] ?? null;
    //        $query->whereNotExists(function ($sub) use ($targetUserId) {
    //            $sub->selectRaw('1')
    //                ->from('matching_users as mu')
    //                ->whereNull('mu.deleted_at')
    //                // mu phải chứa user mục tiêu
    //                ->where(function ($q) use ($targetUserId) {
    //                    $q->where('mu.owner_user_id', $targetUserId)
    //                        ->orWhere('mu.peer_user_id', $targetUserId);
    //                })
    //                //
    //                ->where(function ($q) use ($targetUserId) {
    //                    $q->orWhere(function ($qq) use ($targetUserId) {
    //                        $qq->whereNotNull('live_chat_profiles.user_id')
    //                            ->where(function ($q2) use ($targetUserId) {
    //                                $q2->where(function ($x) use ($targetUserId) {
    //                                    $x->where('mu.owner_user_id', $targetUserId)
    //                                        ->whereColumn('mu.peer_user_id', 'live_chat_profiles.user_id');
    //                                })->orWhere(function ($x) use ($targetUserId) {
    //                                    $x->where('mu.peer_user_id', $targetUserId)
    //                                        ->whereColumn('mu.owner_user_id', 'live_chat_profiles.user_id');
    //                                });
    //                            });
    //                    })
    //                        ->orWhere(function ($qq) use ($targetUserId) {
    //                            $qq->whereNotNull('live_chat_profiles.exhibitor_administrator_id')
    //                                ->where(function ($q2) use ($targetUserId) {
    //                                    $q2->where(function ($x) use ($targetUserId) {
    //                                        $x->where('mu.owner_user_id', $targetUserId)
    //                                            ->whereColumn('mu.peer_user_id', 'live_chat_profiles.exhibitor_administrator_id');
    //                                    })->orWhere(function ($x) use ($targetUserId) {
    //                                        $x->where('mu.peer_user_id', $targetUserId)
    //                                            ->whereColumn('mu.owner_user_id', 'live_chat_profiles.exhibitor_administrator_id');
    //                                    });
    //                                });
    //                        });
    //                });
    //        });
    //    }

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
            'live_chat_profiles.exhibitor_administrator_id',
            'live_chat_profiles.custom_fields'
        ];
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
