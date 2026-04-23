<?php

namespace App\Console\Commands;

use App\Repositories\LiveChatProfilesRepository;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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

    public function handle(): void
    {
        $file = $this->argument('file');
        $this->disk = Storage::disk(self::DISK);
        $file = $this->getFileContent($file);
        $file = $this->decompressGzip($file);

        $repository = app(LiveChatProfilesRepository::class);
        $failedRows = 0;

        foreach ($this->parseCsvRecords($file) as $row) {
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
                        'user_uuid' => $this->normalizeNullableString($row['user_uuid'] ?? null),
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
                        'user_name' => $this->normalizeNullableString($row['name'] ?? null),
                        'user_email' => $this->normalizeNullableString($row['email'] ?? null),
                        'user_company' => $this->normalizeNullableString($row['company_name'] ?? null),
                        'participation_attributes' => $this->normalizeNullableString($row['participation_attribute_value'] ?? null),
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

    /**
     * @return list<array<string, string>>
     */
    private function parseCsvRecords(string $file): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $file);

        if ($lines === false || $lines === []) {
            throw new RuntimeException('CSV file is empty.');
        }

        $headerLine = array_shift($lines);

        if ($headerLine === null) {
            throw new RuntimeException('CSV header row is missing.');
        }

        $headers = str_getcsv($headerLine, ',', '"', '\\');

        if (! is_array($headers) || $headers === []) {
            throw new RuntimeException('Unable to parse CSV header row.');
        }

        $rows = [];
        $buffer = '';

        foreach ($lines as $line) {
            if ($buffer === '' && trim($line) === '') {
                continue;
            }

            $buffer = $buffer === '' ? $line : $buffer."\n".$line;
            $parsedRow = $this->parseRawCsvRecord($buffer, $headers);

            if ($parsedRow === null) {
                continue;
            }

            $rows[] = $parsedRow;
            $buffer = '';
        }

        if (trim($buffer) !== '') {
            throw new RuntimeException('CSV contains an incomplete trailing record.');
        }

        return $rows;
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, string>|null
     */
    private function parseRawCsvRecord(string $record, array $headers): ?array
    {
        $customFieldsIndex = array_search('custom_fields', $headers, true);

        if (! is_int($customFieldsIndex)) {
            throw new RuntimeException('CSV header "custom_fields" is missing.');
        }

        $leadingFields = $this->extractCsvFieldsFromStart($record, $customFieldsIndex);

        if ($leadingFields === null) {
            return null;
        }

        $trailingFields = $this->extractTrailingCsvFields(
            substr($record, $leadingFields['next_offset']),
            array_slice($headers, $customFieldsIndex + 1)
        );

        if ($trailingFields === null) {
            return null;
        }

        $fields = array_merge(
            $leadingFields['fields'],
            [$trailingFields['custom_fields']],
            $trailingFields['fields']
        );

        if (count($fields) !== count($headers)) {
            throw new RuntimeException('CSV field count does not match header count.');
        }

        $row = array_combine($headers, $fields);

        if ($row === false) {
            throw new RuntimeException('Unable to map CSV fields to headers.');
        }

        return $row;
    }

    /**
     * @return array{fields: list<string>, next_offset: int}|null
     */
    private function extractCsvFieldsFromStart(string $record, int $fieldCount): ?array
    {
        if ($fieldCount === 0) {
            return [
                'fields' => [],
                'next_offset' => 0,
            ];
        }

        $fields = [];
        $current = '';
        $inQuotes = false;
        $length = strlen($record);

        for ($index = 0; $index < $length; $index++) {
            $character = $record[$index];

            if ($character === '"') {
                if ($inQuotes && $index + 1 < $length && $record[$index + 1] === '"') {
                    $current .= '""';
                    $index++;

                    continue;
                }

                $inQuotes = ! $inQuotes;
                $current .= $character;

                continue;
            }

            if ($character === ',' && ! $inQuotes) {
                $fields[] = $this->parseCsvField($current);

                if (count($fields) === $fieldCount) {
                    return [
                        'fields' => $fields,
                        'next_offset' => $index + 1,
                    ];
                }

                $current = '';

                continue;
            }

            $current .= $character;
        }

        return null;
    }

    /**
     * @param  list<string>  $headers
     * @return array{custom_fields: string, fields: list<string>}|null
     */
    private function extractTrailingCsvFields(string $record, array $headers): ?array
    {
        if ($headers === []) {
            return [
                'fields' => [],
                'custom_fields' => $record,
            ];
        }

        $pattern = $this->trailingCsvPattern($headers);

        if ($pattern === null) {
            throw new RuntimeException('Unsupported CSV header layout for trailing fields.');
        }

        if (preg_match($pattern, $record, $matches) !== 1) {
            return null;
        }

        $fields = [];

        foreach ($headers as $header) {
            $fields[] = $this->parseCsvField($matches[$header] ?? '');
        }

        return [
            'custom_fields' => $this->parseCsvField($matches['custom_fields'] ?? ''),
            'fields' => $fields,
        ];
    }

    /**
     * @param  list<string>  $headers
     */
    private function trailingCsvPattern(array $headers): ?string
    {
        if ($headers === [
            'display_is_search',
            'exhibitor_administrator_id',
            'last_portal_id',
            'last_event_id',
            'created_at',
            'updated_at',
            'exhibitor_text',
            'email',
            'name',
            'company_name',
            'participation_attribute_value',
        ]) {
            $fieldPattern = '"(?:[^"]|"")*"|[^,"\r\n]*';

            return '/^(?P<custom_fields>.*?),(?P<display_is_search>'.$fieldPattern.'),(?P<exhibitor_administrator_id>'.$fieldPattern.'),(?P<last_portal_id>'.$fieldPattern.'),(?P<last_event_id>'.$fieldPattern.'),(?P<created_at>'.$fieldPattern.'),(?P<updated_at>'.$fieldPattern.'),(?P<exhibitor_text>'.$fieldPattern.'),(?P<email>'.$fieldPattern.'),(?P<name>'.$fieldPattern.'),(?P<company_name>'.$fieldPattern.'),(?P<participation_attribute_value>'.$fieldPattern.')$/s';
        }

        if ($headers === [
            'display_is_search',
            'exhibitor_administrator_id',
            'last_portal_id',
            'last_event_id',
            'created_at',
            'updated_at',
            'exhibitor_text',
            'email',
            'name',
            'company_name',
        ]) {
            $fieldPattern = '"(?:[^"]|"")*"|[^,"\r\n]*';

            return '/^(?P<custom_fields>.*?),(?P<display_is_search>'.$fieldPattern.'),(?P<exhibitor_administrator_id>'.$fieldPattern.'),(?P<last_portal_id>'.$fieldPattern.'),(?P<last_event_id>'.$fieldPattern.'),(?P<created_at>'.$fieldPattern.'),(?P<updated_at>'.$fieldPattern.'),(?P<exhibitor_text>'.$fieldPattern.'),(?P<email>'.$fieldPattern.'),(?P<name>'.$fieldPattern.'),(?P<company_name>'.$fieldPattern.')$/s';
        }

        if ($headers === [
            'exhibitor_administrator_id',
            'last_portal_id',
            'last_event_id',
            'exhibitor_text',
            'email',
            'name',
            'company_name',
        ]) {
            $fieldPattern = '"(?:[^"]|"")*"|[^,"\r\n]*';

            return '/^(?P<custom_fields>.*?),(?P<exhibitor_administrator_id>'.$fieldPattern.'),(?P<last_portal_id>'.$fieldPattern.'),(?P<last_event_id>'.$fieldPattern.'),(?P<exhibitor_text>'.$fieldPattern.'),(?P<email>'.$fieldPattern.'),(?P<name>'.$fieldPattern.'),(?P<company_name>'.$fieldPattern.')$/s';
        }

        return null;
    }

    private function parseCsvField(string $value): string
    {
        $trimmedValue = $value;

        if (str_starts_with($trimmedValue, '"') && str_ends_with($trimmedValue, '"')) {
            $trimmedValue = substr($trimmedValue, 1, -1);

            if ($trimmedValue === false) {
                return '';
            }
        }

        return str_replace('""', '"', $trimmedValue);
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
