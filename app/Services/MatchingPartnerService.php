<?php

namespace App\Services;

use App\Models\CheckinHistory;
use App\Models\LiveChatProfiles;
use App\Models\LiveChatProfileTag;
use App\Models\MatchingCsvDownloadColumn;
use App\Models\MatchingCsvDownloadSetting;
use App\Models\MatchingUser;
use App\Models\NetworkingEventMaster;
use App\Models\ShareProfileField;
use App\Repositories\LiveChatProfileFieldOptionRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use JsonException;

class MatchingPartnerService
{
    private const string CSV_ATTENDEE_CATEGORY_COLUMN_KEY = 'attendee_category';

    private const string DISCOVER_NETWORKING = 'NETWORKING';

    private const string DISCOVER_EXHIBITOR = 'EXHIBITOR';

    private const string DISCOVER_VISITOR = 'VISITOR';

    private const string TYPE_EXHIBITOR = 'exhibitor';

    private const string TYPE_VISITOR = 'visitor';

    private const int MAX_TYPED_RESULTS = 120;

    private const array CUSTOM_FIELDS_FIRST_CSV_COLUMN_KEYS = [
        'pr_free_text',
        'target_industry',
        'what_i_am_looking_for',
        'what_i_am_looking_for_free_text',
    ];

    public function __construct(
        private readonly LiveChatProfileFieldOptionRepository $liveChatProfileFieldOptionRepository
    ) {}

    /**
     * @throws JsonException
     */
    public function getPartners(array $ctx): array
    {
        if ($this->isPartnerTypeFilterEnabled($ctx)) {
            return $this->getPaginatedPartnersByType($ctx);
        }

        $networking = $this->getNetworking($ctx);

        return [
            [
                'discover_type' => self::DISCOVER_NETWORKING,
                'list' => $networking,
            ],
            [
                'discover_type' => self::DISCOVER_EXHIBITOR,
                'items' => [],
            ],
            [
                'discover_type' => self::DISCOVER_VISITOR,
                'items' => [],
            ],
        ];
    }

    public function getCsvDownloadData(array $ctx): array
    {
        $language = $this->normalizeLanguage($ctx['language'] ?? 'jpn');
        $csvDownloadSetting = $this->getCsvDownloadSetting();

        if (! $csvDownloadSetting?->is_enabled) {
            return [];
        }

        $requestedUuids = collect($ctx['live_chat_user_uuids'] ?? [])
            ->filter(static fn (mixed $uuid): bool => is_string($uuid) && trim($uuid) !== '')
            ->map(static fn (string $uuid): string => trim($uuid))
            ->unique()
            ->values()
            ->all();

        if ($requestedUuids === []) {
            return [];
        }

        $profiles = $this->getCsvDownloadProfiles($ctx, $requestedUuids);
        $columns = $this->getCsvDownloadColumns();
        $participationAttributeLabels = $this->getParticipationAttributeLabelsByLanguage($language);

        return [
            'headers' => $this->resolveCsvDownloadHeaders($columns, $language),
            'users' => $profiles
                ->map(fn (LiveChatProfiles $profile): array => $this->buildCsvDownloadRow(
                    $profile,
                    $columns,
                    $language,
                    $participationAttributeLabels
                ))
                ->values()
                ->all(),
        ];
    }

    private function getPaginatedPartnersByType(array $ctx): array
    {
        $isExhibitor = $this->resolveIsExhibitorType($ctx);
        $page = max((int) ($ctx['page'] ?? 1), 1);
        $perPage = max((int) ($ctx['per_page'] ?? 1), 1);
        $seed = $this->resolveSeed($ctx);
        $seedNumber = $this->resolveSeedNumber($seed);
        $typedPartnerQuery = $this->getTypedPartnerQuery($ctx, $isExhibitor);
        $total = (clone $typedPartnerQuery)->count();
        $limitedTotal = min($total, self::MAX_TYPED_RESULTS);

        $orderedIds = $typedPartnerQuery
            ->distinct()
            ->orderByRaw('(live_chat_profiles.id * ? + ?) % 2147483647', [$seedNumber, $seedNumber % 97])
            ->orderBy('live_chat_profiles.id')
            ->limit(self::MAX_TYPED_RESULTS)
            ->pluck('live_chat_profiles.id')
            ->values();

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
        $paginator = new LengthAwarePaginator($items, $limitedTotal, $perPage, $page);

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

        $this->applyRequiredProfileFieldsFilter($query);

        if (! empty($ctx['keyword'])) {
            $keyword = $ctx['keyword'];

            $query->where(function ($q) use ($keyword) {
                $q->where('nickname', 'like', '%'.$keyword.'%')
                    ->orWhere('company', 'like', '%'.$keyword.'%');
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
            'custom_fields',
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

    /**
     * @param  array<int, string>  $requestedUuids
     */
    private function getCsvDownloadProfiles(array $ctx, array $requestedUuids): Collection
    {
        if ($requestedUuids === []) {
            return collect();
        }

        $query = LiveChatProfiles::query()
            ->whereNull('deleted_at')
            ->where('live_chat_data_source_id', $ctx['data_source_id'])
            ->where('last_event_id', $ctx['event_id'])
            ->whereIn('uuid', $requestedUuids);

        $this->applyRequiredProfileFieldsFilter($query);

        $profiles = $query->get($this->csvDownloadSelectColumns());

        $profilesByUuid = $profiles->keyBy('uuid');

        return collect($requestedUuids)
            ->map(fn (string $uuid): ?LiveChatProfiles => $profilesByUuid->get($uuid))
            ->filter()
            ->values();
    }

    /**
     * @param  array<int, string>  $sourceUuids
     * @return array<int, string>
     */
    private function resolveCsvDownloadTargetUuids(array $sourceUuids, int $eventId): array
    {
        $rows = MatchingUser::query()
            ->whereNull('deleted_at')
            ->where('event_id', $eventId)
            ->when(
                $sourceUuids !== [],
                fn ($query) => $query->where(function ($builder) use ($sourceUuids) {
                    $builder->whereIn('owner_uuid', $sourceUuids)
                        ->orWhereIn('peer_uuid', $sourceUuids);
                })
            )
            ->orderBy('id')
            ->get(['owner_uuid', 'peer_uuid']);

        $resolved = [];

        foreach ($rows as $row) {
            $ownerUuid = trim((string) ($row->owner_uuid ?? ''));
            $peerUuid = trim((string) ($row->peer_uuid ?? ''));

            if ($sourceUuids === []) {
                foreach ([$ownerUuid, $peerUuid] as $uuid) {
                    if ($uuid !== '' && ! isset($resolved[$uuid])) {
                        $resolved[$uuid] = $uuid;
                    }
                }

                continue;
            }

            if (in_array($ownerUuid, $sourceUuids, true) && $peerUuid !== '' && ! isset($resolved[$peerUuid])) {
                $resolved[$peerUuid] = $peerUuid;
            }

            if (in_array($peerUuid, $sourceUuids, true) && $ownerUuid !== '' && ! isset($resolved[$ownerUuid])) {
                $resolved[$ownerUuid] = $ownerUuid;
            }
        }

        return array_values($resolved);
    }

    private function isPartnerTypeFilterEnabled(array $ctx): bool
    {
        return in_array($ctx['type'] ?? null, [self::TYPE_EXHIBITOR, self::TYPE_VISITOR], true);
    }

    private function resolveIsExhibitorType(array $ctx): bool
    {
        return ($ctx['type'] ?? null) === self::TYPE_EXHIBITOR;
    }

    /**
     * @param  Collection<int, MatchingCsvDownloadColumn>  $columns
     * @return array<int, string>
     */
    private function resolveCsvDownloadHeaders(Collection $columns, string $language): array
    {
        $labelKey = $language === 'eng' ? 'label_eng' : 'label_jpn';

        return $columns
            ->map(static fn (MatchingCsvDownloadColumn $column): string => (string) $column->{$labelKey})
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, MatchingCsvDownloadColumn>  $columns
     * @param  array<string, string>  $participationAttributeLabels
     * @return array<int, string>
     */
    private function buildCsvDownloadRow(
        LiveChatProfiles $profile,
        Collection $columns,
        string $language,
        array $participationAttributeLabels
    ): array {
        $resolvedCustomFields = collect($this->liveChatProfileFieldOptionRepository->getResolvedCustomFields(
            (int) $profile->profile_id,
            $language,
            is_array($profile->custom_fields) ? $profile->custom_fields : null
        ))->keyBy('field_key');

        return $columns
            ->map(function (MatchingCsvDownloadColumn $column) use (
                $profile,
                $language,
                $resolvedCustomFields,
                $participationAttributeLabels
            ): string {
                if ($column->column_key === 'participation_attributes') {
                    return $this->normalizeCsvDownloadCell(
                        $this->resolveParticipationAttributesValue($profile, $participationAttributeLabels)
                    );
                }

                if ($column->column_key === self::CSV_ATTENDEE_CATEGORY_COLUMN_KEY) {
                    return $this->resolveCsvDownloadAttendeeCategoryValue($profile, $language);
                }

                if ($column->type === 'profile') {
                    return $this->normalizeCsvDownloadCell($this->resolveCsvDownloadProfileValue($profile, (string) $column->profile_key));
                }

                $fieldKey = trim((string) ($column->custom_field_key ?? ''));

                if ($fieldKey === '') {
                    return '';
                }

                $columnKey = (string) $column->column_key;

                if ($this->shouldUseCustomFieldsFirstCsvValue($columnKey)) {
                    $customFieldValue = $this->resolveCsvDownloadPreferredCustomFieldValue($profile, $fieldKey);

                    if ($customFieldValue !== null) {
                        return $this->normalizeCsvDownloadCell($customFieldValue);
                    }
                }

                /** @var array<string, mixed>|null $field */
                $field = $resolvedCustomFields->get($fieldKey);

                return $this->normalizeCsvDownloadCell($this->resolveCsvDownloadCustomFieldValue($field));
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $participationAttributeLabels
     */
    private function resolveParticipationAttributesValue(
        LiveChatProfiles $profile,
        array $participationAttributeLabels
    ): string {
        $value = trim((string) ($profile->participation_attributes ?? ''));

        if ($value === '') {
            return '';
        }

        return $participationAttributeLabels[$value] ?? $value;
    }

    private function resolveCsvDownloadAttendeeCategoryValue(LiveChatProfiles $profile, string $language): string
    {
        $isExhibitor = (bool) ($profile->is_exhibitor ?? false);

        if ($language === 'eng') {
            return $isExhibitor ? 'Exhibitor' : 'Visitor';
        }

        return $isExhibitor ? '出展者' : '来場者';
    }

    /**
     * @return array<string, string>
     */
    private function getParticipationAttributeLabelsByLanguage(string $language): array
    {
        $languageId = $language === 'eng'
            ? (int) config('language.eng', 2)
            : (int) config('language.jpn', 1);
        $label = $language === 'eng' ? 'Participation Attributes' : '参加属性';

        $field = ShareProfileField::query()
            ->where('language_id', $languageId)
            ->where('label', $label)
            ->latest('id')
            ->first(['selector_items']);

        if (! $field instanceof ShareProfileField || ! is_array($field->selector_items)) {
            return [];
        }

        return collect($field->selector_items)
            ->filter(static fn (mixed $item): bool => is_array($item))
            ->mapWithKeys(function (array $item): array {
                $key = trim((string) ($item['key'] ?? ''));
                $value = trim((string) ($item['value'] ?? ''));

                if ($key === '' || $value === '') {
                    return [];
                }

                return [$key => $value];
            })
            ->all();
    }

    private function getNetworking(array $ctx): array
    {
        $currentActorId = $this->resolveCurrentActorId($ctx);
        if (! $currentActorId) {
            return [];
        }

        $query = CheckinHistory::query()
            ->limit(config('constants.NET_WORKING_LIMIT') ?? 5)
            ->where('user_id', $currentActorId);

        $myNames = $query
            ->distinct()
            ->pluck('checkin_app_user_name')
            ->values();

        $groupDefinitions = $this->buildNetworkingGroupDefinitions($myNames, (int) ($ctx['language_id'] ?? config('language.jpn', 1)));

        if ($groupDefinitions->isEmpty()) {
            return [];
        }

        $groups = [];

        foreach ($groupDefinitions as $groupDefinition) {
            $qUser = CheckinHistory::query()
                ->whereNotNull('checkin_histories.user_id')
                ->whereIn('checkin_histories.checkin_app_user_name', $groupDefinition['match_names'])
                ->join(
                    'live_chat_profiles',
                    'checkin_histories.user_id',
                    '=',
                    'live_chat_profiles.user_id'
                );

            $qUser->where('checkin_histories.user_id', '<>', $currentActorId);
            $this->applyRequiredProfileFieldsFilter($qUser);

            if (! empty($ctx['keyword'])) {
                $keyword = $ctx['keyword'];
                $qUser->where(function ($sub) use ($keyword) {
                    $sub->where('nickname', 'like', "%{$keyword}%")
                        ->orWhere('company', 'like', "%{$keyword}%");
                });
            }

            $this->applyOptionValueFilter($qUser, $ctx['option_values'] ?? []);
            $this->removeUserTalked($qUser, $ctx);

            $checkins = $qUser
                ->distinct()
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
                } elseif (! is_array($item->custom_fields ?? null)) {
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
                'checkin_app_user_name' => $groupDefinition['display_name'],
                'items' => $checkins,
            ];
        }

        return $groups;
    }

    private function buildNetworkingGroupDefinitions(Collection $myNames, int $languageId): Collection
    {
        $normalizedNames = $myNames
            ->filter(static fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->map(static fn (string $name): string => trim($name))
            ->unique()
            ->values();

        if ($normalizedNames->isEmpty()) {
            return collect();
        }

        $masterRows = NetworkingEventMaster::query()
            ->select(['event_name_ja', 'event_name_en', 'checkin_app_user_name', 'event_date', 'matching_display_time'])
            ->get();

        $groupDefinitions = [];

        foreach ($normalizedNames as $name) {
            $groupDefinition = $this->resolveNetworkingGroupDefinition($name, $masterRows, $languageId);

            if ($groupDefinition === null) {
                continue;
            }

            $displayName = $groupDefinition['display_name'];

            if (isset($groupDefinitions[$displayName])) {
                $groupDefinitions[$displayName]['match_names'] = collect([
                    ...$groupDefinitions[$displayName]['match_names'],
                    ...$groupDefinition['match_names'],
                ])->unique()->values()->all();

                continue;
            }

            $groupDefinitions[$displayName] = [
                'display_name' => $displayName,
                'match_names' => $groupDefinition['match_names'],
            ];
        }

        return collect(array_values($groupDefinitions));
    }

    /**
     * @return array{display_name: string, match_names: array<int, string>}|null
     */
    private function resolveNetworkingGroupDefinition(string $name, Collection $masterRows, int $languageId): ?array
    {
        $matchedMaster = $masterRows->first(function (NetworkingEventMaster $master) use ($name): bool {
            return $this->networkingNamesMatch($master->event_name_ja, $name)
                || $this->networkingNamesMatch($master->event_name_en, $name)
                || $this->networkingNamesMatch($master->checkin_app_user_name, $name);
        });

        if ($matchedMaster instanceof NetworkingEventMaster) {
            if (! $this->isNetworkingEventVisible($matchedMaster)) {
                return null;
            }

            $localizedEventName = $this->resolveNetworkingLocalizedEventName($matchedMaster, $languageId);

            return [
                'display_name' => $localizedEventName ?? $name,
                'match_names' => $this->resolveNetworkingGroupMatchNames($matchedMaster, $name, $masterRows),
            ];
        }

        return [
            'display_name' => $name,
            'match_names' => [$name],
        ];
    }

    private function isNetworkingEventVisible(NetworkingEventMaster $master): bool
    {
        $eventDate = $this->normalizeNetworkingName($master->event_date);
        $matchingDisplayTime = $this->normalizeNetworkingName($master->matching_display_time);

        if ($eventDate === null || $matchingDisplayTime === null) {
            return false;
        }

        $displayAt = CarbonImmutable::parse(
            sprintf('%s %s', $eventDate, $matchingDisplayTime),
            config('app.timezone')
        );

        return now()->greaterThanOrEqualTo($displayAt);
    }

    private function resolveNetworkingLocalizedEventName(NetworkingEventMaster $master, int $languageId): ?string
    {
        if ($languageId === (int) config('language.eng', 2)) {
            $eventNameEn = is_string($master->event_name_en) ? trim($master->event_name_en) : '';

            if ($eventNameEn !== '') {
                return $eventNameEn;
            }
        }

        $eventNameJa = is_string($master->event_name_ja) ? trim($master->event_name_ja) : '';

        if ($eventNameJa !== '') {
            return $eventNameJa;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function resolveNetworkingGroupMatchNames(
        NetworkingEventMaster $matchedMaster,
        string $fallbackName,
        Collection $masterRows
    ): array {
        $eventNameJa = $this->normalizeNetworkingName($matchedMaster->event_name_ja);
        $eventNameEn = $this->normalizeNetworkingName($matchedMaster->event_name_en);

        $matchedNames = $masterRows
            ->filter(function (NetworkingEventMaster $master) use ($eventNameJa, $eventNameEn): bool {
                if (! $this->isNetworkingEventVisible($master)) {
                    return false;
                }

                return ($eventNameJa !== null && $this->networkingNamesMatch($master->event_name_ja, $eventNameJa))
                    || ($eventNameEn !== null && $this->networkingNamesMatch($master->event_name_en, $eventNameEn));
            })
            ->flatMap(function (NetworkingEventMaster $master): array {
                return [
                    $master->event_name_ja,
                    $master->event_name_en,
                    $master->checkin_app_user_name,
                ];
            })
            ->filter(static fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->map(static fn (string $name): string => trim($name))
            ->unique()
            ->values()
            ->all();

        if (! in_array($fallbackName, $matchedNames, true)) {
            $matchedNames[] = $fallbackName;
        }

        return $matchedNames;
    }

    private function networkingNamesMatch(mixed $left, mixed $right): bool
    {
        $normalizedLeft = $this->normalizeNetworkingName($left);
        $normalizedRight = $this->normalizeNetworkingName($right);

        return $normalizedLeft !== null && $normalizedRight !== null && $normalizedLeft === $normalizedRight;
    }

    private function normalizeNetworkingName(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }

        $normalizedName = trim($name);

        return $normalizedName !== '' ? $normalizedName : null;
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
        if (! $currentId) {
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
                })
                ->where(function ($q) {
                    $q->whereIn('mu.status', [3, 4])
                        ->orWhere('mu.appointment_status', 'approved');
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
        $this->applyRequiredProfileFieldsFilter($query);
        if (! empty($ctx['keyword'])) {
            $keyword = $ctx['keyword'];

            $query->where(function ($q) use ($keyword) {
                $q->where('nickname', 'like', '%'.$keyword.'%')
                    ->orWhere('company', 'like', '%'.$keyword.'%');
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
                'custom_fields',
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
        $this->applyRequiredProfileFieldsFilter($query);
        if (! empty($ctx['keyword'])) {
            $keyword = $ctx['keyword'];

            $query->where(function ($q) use ($keyword) {
                $q->where('nickname', 'like', '%'.$keyword.'%')
                    ->orWhere('company', 'like', '%'.$keyword.'%');
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
                'custom_fields',
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
            'live_chat_profiles.custom_fields',
        ];
    }

    private function csvDownloadSelectColumns(): array
    {
        return [
            'id',
            'profile_id',
            'uuid',
            'nickname',
            'company',
            'mail_address',
            'is_exhibitor',
            'custom_fields',
            'user_name',
            'user_email',
            'user_company',
            'participation_attributes',
        ];
    }

    private function applyRequiredProfileFieldsFilter(Builder $query): void
    {
        $query->whereNotNull('live_chat_profiles.nickname')
            ->whereNotNull('live_chat_profiles.company')
            ->whereNotNull('live_chat_profiles.custom_fields');
    }

    private function resolveCurrentActorId(array $ctx): ?int
    {
        $userId = $ctx['user_id'] ?? null;
        if (is_numeric($userId) && (int) $userId > 0) {
            return (int) $userId;
        }

        return null;
    }

    private function normalizeLanguage(mixed $language): string
    {
        return strtolower(trim((string) $language)) === 'eng' ? 'eng' : 'jpn';
    }

    private function getCsvDownloadSetting(): ?MatchingCsvDownloadSetting
    {
        return MatchingCsvDownloadSetting::query()
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, MatchingCsvDownloadColumn>
     */
    private function getCsvDownloadColumns(): Collection
    {
        return MatchingCsvDownloadColumn::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function resolveCsvDownloadProfileValue(LiveChatProfiles $profile, string $key): ?string
    {
        return match ($key) {
            'user_name' => $profile->user_name ?? $profile->nickname,
            'user_email' => $profile->user_email ?? $profile->mail_address,
            'user_company' => $profile->user_company ?? $profile->company,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>|null  $field
     */
    private function resolveCsvDownloadCustomFieldValue(?array $field): ?string
    {
        if ($field === null) {
            return null;
        }

        $resolvedValue = collect($field['values'] ?? [])
            ->map(function (mixed $value): string {
                if (! is_array($value)) {
                    return '';
                }

                $label = array_key_exists('label', $value)
                    ? $value['label']
                    : ($value['option_value'] ?? '');

                return is_string($label) ? trim($label) : '';
            })
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->implode(', ');

        return $resolvedValue !== '' ? $resolvedValue : null;
    }

    private function resolveCsvDownloadPreferredCustomFieldValue(LiveChatProfiles $profile, string $fieldKey): ?string
    {
        $customFields = $profile->custom_fields;

        if (! is_array($customFields) || ! array_key_exists($fieldKey, $customFields)) {
            return null;
        }

        $value = $customFields[$fieldKey];

        if (! is_scalar($value)) {
            return null;
        }

        $normalizedValue = trim((string) $value);
        if ($normalizedValue === '') {
            return '';
        }

        if (str_starts_with(strtolower($normalizedValue), 'option')) {
            return null;
        }

        if (str_starts_with(strtolower($normalizedValue), 'additional')) {
            return null;
        }

        return $normalizedValue;
    }

    private function shouldUseCustomFieldsFirstCsvValue(string $columnKey): bool
    {
        return in_array($columnKey, self::CUSTOM_FIELDS_FIRST_CSV_COLUMN_KEYS, true);
    }

    private function normalizeCsvDownloadCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $normalized !== null && $normalized !== '' ? $normalized : '';
    }

    private function hydrateInformationField(Collection $profiles): Collection
    {
        return $profiles->map(function ($row) {
            $customFields = $row->custom_fields ?? null;

            if (is_string($customFields)) {
                $decoded = json_decode($customFields, true);
                $customFields = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
            }

            if (! is_array($customFields)) {
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
