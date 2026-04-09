<?php

namespace App\Console\Commands;

use App\Repositories\LiveChatProfilesRepository;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Exception;
use League\Csv\Reader;

class ImportLiveChatProfile extends Command implements ShouldBeUnique, ShouldQueue
{
    use CsvTrait;

    private const int MAX_OPTION_VALUE_LENGTH = 500;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live_chat_profiles {file : The CSV file to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    const string DISK = 's3';

    /**
     * Execute the console command.
     *
     * @throws Exception
     */
    public function handle()
    {
        $file = $this->argument('file');
        $this->disk = Storage::disk(self::DISK);
        $file = $this->getFileContent($file);
        $file = $this->decompressGzip($file);
        $csv = Reader::fromString($file)->skipEmptyRecords()->setHeaderOffset(0);

        $repository = app(LiveChatProfilesRepository::class);
        $failedRows = 0;

        foreach ($csv->getRecords() as $row) {
            try {
                DB::transaction(function () use ($repository, $row) {
                    $isExhibitor = $this->isExhibitorText($row['exhibitor_text'] ?? null);

                    $attributes = [
                        'uuid' => $row['uuid'] ?? null,
                        'user_id' => $this->toNullableInt($row['user_id'] ?? null),
                        'live_chat_data_source_id' => $this->toInt($row['live_chat_data_source_id'] ?? null),
                        'live_chat_user_id' => $row['live_chat_user_id'] ?? null,
                    ];
                    $profile = $repository->updateOrCreate($attributes, [
                        'profile_id' => $this->toNullableInt($row['id'] ?? null),
                        'user_id' => $this->toNullableInt($row['user_id'] ?? null),
                        'live_chat_data_source_id' => $this->toInt($row['live_chat_data_source_id'] ?? null),
                        'live_chat_user_id' => $row['live_chat_user_id'] ?? null,
                        'uuid' => $row['uuid'] ?? null,
                        'nickname' => $row['nickname'] ?? null,
                        'icon_image' => $this->normalizeJsonField($row['icon_image'] ?? null),
                        'background_image' => $this->normalizeJsonField($row['background_image'] ?? null),
                        'introduction' => $row['introduction'] ?? null,
                        'mail_address' => $row['mail_address'] ?? null,
                        'company' => $row['company'] ?? null,
                        'custom_fields' => $this->normalizeJsonField($row['custom_fields'] ?? null),
                        'exhibitor_administrator_id' => $this->toNullableInt($row['exhibitor_administrator_id'] ?? null),
                        'last_portal_id' => $this->toInt($row['last_portal_id'] ?? null),
                        'last_event_id' => $this->toInt($row['last_event_id'] ?? null),
                        'is_exhibitor' => $isExhibitor,
                    ]);

                    // Persist selected dropdown options into live_chat_profile_field_options
                    $this->syncProfileFieldOptionsFromCustomFields(
                        profileId: (int) ($profile->profile_id ?? 0),
                        customFieldsRaw: $row['custom_fields'] ?? null
                    );
                });
            } catch (\Throwable $exception) {
                $failedRows++;

                $this->warn(sprintf(
                    'Skipped live chat profile row. profile_id=%s uuid=%s reason=%s',
                    $row['id'] ?? 'n/a',
                    $row['uuid'] ?? 'n/a',
                    $exception->getMessage()
                ));
            }
        }

        $this->info('Import completed successfully.');

        if ($failedRows > 0) {
            $this->warn(sprintf('Skipped %d failed row(s).', $failedRows));
        }
    }

    private function toInt(mixed $value, int $default = 0): int
    {
        $normalized = $this->normalizeIntegerString($value);

        if ($normalized === null) {
            return $default;
        }

        return (int) $normalized;
    }

    private function toNullableInt(mixed $value): ?int
    {
        $normalized = $this->normalizeIntegerString($value);

        if ($normalized === null) {
            return null;
        }

        return (int) $normalized;
    }

    private function normalizeIntegerString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '' || strcasecmp($trimmed, 'null') === 0) {
            return null;
        }

        $normalized = str_replace([',', ' '], '', $trimmed);

        if (preg_match('/^-?\d+$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $v = trim((string) $value);
        if ($v === '' || strcasecmp($v, 'null') === 0) {
            return null;
        }

        return $v;
    }

    private function isExhibitorText(mixed $exhibitorText): bool
    {
        $normalizedExhibitorText = $this->normalizeNullableString($exhibitorText);

        if ($normalizedExhibitorText === null) {
            return false;
        }

        $expectedExhibitorText = $this->normalizeNullableString(
            config('constants.LIVE_CHAT_EXHIBITOR_TEXT', '出展者')
        );

        if ($expectedExhibitorText === null) {
            return false;
        }

        return $normalizedExhibitorText === $expectedExhibitorText;
    }

    /**
     * Normalize JSON field from CSV.
     * - If null/empty/"null" string: return null
     * - If valid JSON string: decode and return array
     * - If already array: return as-is
     */
    private function normalizeJsonField(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $v = trim((string) $value);
        if ($v === '' || strcasecmp($v, 'null') === 0) {
            return null;
        }

        $decoded = json_decode($v, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return null;
    }

    /**
     * Save profile selections into live_chat_profile_field_options from CSV custom_fields.
     *
     * Your CSV custom_fields format is a JSON object:
     * {
     *   "email": "a@b.com",
     *   "gender": "female",
     *   "additional1769...": "option1769...",
     *   "address": { ... }
     * }
     *
     * We store:
     * - value must be scalar (string/number/bool)
     * - option-like values (option123...)
     * - text values for dynamic additional fields (additional...)
     *
     * Table constraint: unique(profile_id, field_key) => one selection per field key.
     */
    private function syncProfileFieldOptionsFromCustomFields(int $profileId, mixed $customFieldsRaw): void
    {
        if ($profileId <= 0) {
            return;
        }

        $customFields = $this->normalizeCustomFields($customFieldsRaw);

        if ($customFields === []) {
            return;
        }

        $now = now();

        foreach ($customFields as $fieldKey => $value) {
            $fieldKey = trim((string) $fieldKey);
            if ($fieldKey === '') {
                continue;
            }

            // Skip nested objects/arrays (e.g. address)
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $optionValue = trim((string) $value);
            if ($optionValue === '' || strcasecmp($optionValue, 'null') === 0) {
                continue;
            }

            if (! $this->shouldPersistFieldValue($fieldKey, $optionValue)) {
                continue;
            }

            $persistedOptionValue = $this->resolvePersistedOptionValue($optionValue);
            $optionId = $persistedOptionValue !== null && $this->isOptionLikeValue($persistedOptionValue)
                ? $this->extractOptionId($persistedOptionValue)
                : null;

            DB::table('live_chat_profile_field_options')->updateOrInsert(
                [
                    'profile_id' => $profileId,
                    'field_key' => $fieldKey,
                ],
                [
                    'option_id' => $optionId,
                    'option_value' => $persistedOptionValue,
                    'updated_at' => $now,
                    'created_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }

    private function resolvePersistedOptionValue(string $optionValue): ?string
    {
        if (mb_strlen($optionValue) > self::MAX_OPTION_VALUE_LENGTH) {
            return null;
        }

        return $optionValue;
    }

    private function shouldPersistFieldValue(string $fieldKey, string $optionValue): bool
    {
        if ($this->isOptionLikeValue($optionValue)) {
            return true;
        }

        return str_starts_with(strtolower($fieldKey), 'additional');
    }

    private function isOptionLikeValue(string $optionValue): bool
    {
        return str_starts_with(strtolower($optionValue), 'option');
    }

    /**
     * Normalize custom_fields to associative array.
     * - If it's already an array: return it
     * - If it's a JSON string: decode it
     */
    private function normalizeCustomFields(mixed $customFieldsRaw): array
    {
        if (is_array($customFieldsRaw)) {
            return $customFieldsRaw;
        }

        if (is_string($customFieldsRaw)) {
            $raw = trim($customFieldsRaw);
            if ($raw === '' || strcasecmp($raw, 'null') === 0) {
                return [];
            }

            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Extract numeric option_id from option_value like "option17696572283514".
     * Fallback to 0 when no digits found.
     */
    private function extractOptionId(string $optionValue): int
    {
        if (preg_match('/(\d+)/', $optionValue, $m) === 1) {
            return (int) $m[1];
        }

        return 0;
    }
}
