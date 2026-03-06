<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Exception;
use League\Csv\Reader;
use Throwable;

final class ImportChatProfileContentsCommand extends Command implements ShouldBeUnique, ShouldQueue
{
    use CsvTrait;

    protected $signature = 'chat_profile_contents
        {file : The CSV/TSV file path to import (on the selected disk)}
        {--disk=s3 : Storage disk name (e.g. s3, local)}
        {--chunk=500 : Number of rows per batch upsert}';

    protected $description = 'Import data into chat_profile_contents table from CSV/TSV. Keep single row per field_key and update in place.';

    public const string DISK = 's3';

    private const string TARGET_TABLE = 'chat_profile_contents';
    private const string SINGLE_OPTION_VALUE = '__field_meta__';

    private const int FIELD_KEY_LIMIT_FOR_IN_CLAUSE = 500;

    /**
     * Storage disk instance (set at runtime by CsvTrait usage).
     *
     * @var mixed
     */
    protected $disk;

    /**
     * Execute the console command.
     *
     * @throws Exception
     */
    public function handle(): int
    {
        Log::info('ImportChatProfileContentsCommand started');
        $filePath = (string) $this->argument('file');
        $diskName = (string) ($this->option('disk') ?: self::DISK);

        $chunkSize = (int) ($this->option('chunk') ?? 500);
        if ($chunkSize < 1) {
            $this->error('--chunk must be >= 1');

            return self::INVALID;
        }

        $this->disk = Storage::disk($diskName);

        $this->info('Import chat_profile_contents');
        $this->line("Disk: {$diskName}");
        $this->line("File: {$filePath}");
        $this->line("Chunk: {$chunkSize}");

        try {
            $file = $this->getFileContent($filePath);

            if (str_ends_with($filePath, '.gz')) {
                $file = $this->decompressGzip($file);
            }

            $delimiter = $this->detectDelimiter($file);

            $csv = Reader::fromString($file)
                ->setDelimiter($delimiter)
                ->skipEmptyRecords()
                ->setHeaderOffset(0);

            $now = new DateTimeImmutable;
            $nowStr = $now->format('Y-m-d H:i:s');

            // Track what (field_key, option_value) exists in CSV
            // $csvPairs[field_key][option_value] = true
            $csvPairs = [];
            $touchedFieldKeys = [];

            $buffer = [];
            $imported = 0;
            $skipped = 0;

            foreach ($csv->getRecords() as $i => $row) {
                $lineNo = (int) $i + 2;

                $mappedRows = $this->mapRowToMany($row, $now, $lineNo);
                if (empty($mappedRows)) {
                    $skipped++;

                    continue;
                }

                foreach ($mappedRows as $r) {
                    $r['deleted_at'] = null;

                    $fieldKey = (string) $r['field_key'];
                    $optionValue = (string) $r['option_value'];

                    $touchedFieldKeys[$fieldKey] = true;
                    $csvPairs[$fieldKey][$optionValue] = true;

                    $buffer[] = $r;

                    if (count($buffer) >= $chunkSize) {
                        $imported += $this->flush($buffer);
                        $buffer = [];
                    }
                }
            }

            if (count($buffer) > 0) {
                $imported += $this->flush($buffer);
            }

            // Soft delete missing rows (only for field_key that appeared in this import).
            $deleted = 0;

            $fieldKeys = array_keys($touchedFieldKeys);
            if (! empty($fieldKeys)) {
                foreach (array_chunk($fieldKeys, self::FIELD_KEY_LIMIT_FOR_IN_CLAUSE) as $fieldKeyChunk) {
                    $dbRows = DB::table(self::TARGET_TABLE)
                        ->select(['field_key', 'option_value'])
                        ->whereNull('deleted_at')
                        ->whereIn('field_key', $fieldKeyChunk)
                        ->get();

                    $toDeleteByField = [];
                    foreach ($dbRows as $dbRow) {
                        $fk = (string) $dbRow->field_key;
                        $ov = (string) $dbRow->option_value;

                        if (! isset($csvPairs[$fk][$ov])) {
                            $toDeleteByField[$fk][] = $ov;
                        }
                    }

                    foreach ($toDeleteByField as $fk => $optionValues) {
                        foreach (array_chunk($optionValues, $chunkSize) as $ovChunk) {
                            $deleted += DB::table(self::TARGET_TABLE)
                                ->where('field_key', $fk)
                                ->whereIn('option_value', $ovChunk)
                                ->whereNull('deleted_at')
                                ->update([
                                    'deleted_at' => $nowStr,
                                    'updated_at' => $nowStr,
                                ]);
                        }
                    }
                }
            }

            $hardDeleted = $this->hardDeleteDuplicateRowsByFieldKey();
            $this->info("Import completed successfully. imported={$imported}, skipped={$skipped}, soft_deleted={$deleted}, hard_deleted={$hardDeleted}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function detectDelimiter(string $file): string
    {
        $firstLine = strtok($file, "\n") ?: '';
        $tabCount = substr_count($firstLine, "\t");
        $commaCount = substr_count($firstLine, ',');

        return $tabCount > $commaCount ? "\t" : ',';
    }

    /**
     * Support:
     * 1) New format: field_key, option_value, label_eng, label_jpn, ...
     * 2) Legacy format: json column contains schema with custom_fields[].options[]
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapRowToMany(array $row, DateTimeImmutable $now, int $lineNo): array
    {
        // Case 1: new format (direct columns)
        $fieldKey = trim((string) ($row['field_key'] ?? $row['fieldKey'] ?? ''));
        if ($fieldKey !== '') {
            $labelEng = trim((string) ($row['label_eng'] ?? $row['labelEng'] ?? ''));
            $labelJpn = trim((string) ($row['label_jpn'] ?? $row['labelJpn'] ?? ''));

            if ($labelEng === '' && $labelJpn !== '') {
                $labelEng = $labelJpn;
            }
            if ($labelJpn === '' && $labelEng !== '') {
                $labelJpn = $labelEng;
            }

            if ($labelEng === '' && $labelJpn === '') {
                $this->warn("Line {$lineNo}: missing both label_eng/label_jpn => skipped");

                return [];
            }

            $descEng = trim((string) ($row['description_eng'] ?? $row['desc_eng'] ?? $row['descriptionEng'] ?? $row['descEng'] ?? ''));
            $descJpn = trim((string) ($row['description_jpn'] ?? $row['desc_jpn'] ?? $row['descriptionJpn'] ?? $row['descJpn'] ?? ''));

            if ($descEng === '' && $descJpn !== '') {
                $descEng = $descJpn;
            }
            if ($descJpn === '' && $descEng !== '') {
                $descJpn = $descEng;
            }

            $createdAt = $this->toNullableDateTimeString($row['created_at'] ?? null) ?? $now->format('Y-m-d H:i:s');
            $updatedAt = $this->toNullableDateTimeString($row['updated_at'] ?? null) ?? $now->format('Y-m-d H:i:s');

            $languageSettingJson = $this->buildLanguageSettingJson($labelEng, $labelJpn, $descEng, $descJpn);

            return [[
                'field_key' => $fieldKey,
                'option_value' => self::SINGLE_OPTION_VALUE,
                'label_eng' => $labelEng,
                'label_jpn' => $labelJpn,
                'language_setting' => $languageSettingJson,
                'sort_order' => 0,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'deleted_at' => null,
            ]];
        }

        // Case 2: legacy format (json column)
        $jsonStr = trim((string) ($row['json'] ?? ''));
        if ($jsonStr === '') {
            $this->warn("Line {$lineNo}: missing field_key/option_value and missing json => skipped");

            return [];
        }

        $decoded = json_decode($jsonStr, true);
        if (! is_array($decoded)) {
            $this->warn("Line {$lineNo}: invalid json => skipped. error=".json_last_error_msg());

            return [];
        }

        $createdAt = $this->toNullableDateTimeString($row['created_at'] ?? null) ?? $now->format('Y-m-d H:i:s');
        $updatedAt = $this->toNullableDateTimeString($row['updated_at'] ?? null) ?? $now->format('Y-m-d H:i:s');

        $rows = $this->extractRowsFromSchemaJson($decoded, $createdAt, $updatedAt);

        if (empty($rows)) {
            $this->warn("Line {$lineNo}: json parsed but no importable fields found => skipped");
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<int, array<string, mixed>>
     */
    private function extractRowsFromSchemaJson(array $decoded, string $createdAt, string $updatedAt): array
    {
        $rows = [];

        $customFields = $decoded['custom_fields'] ?? null;
        if (! is_array($customFields)) {
            return [];
        }

        foreach ($customFields as $cf) {
            if (! is_array($cf)) {
                continue;
            }

            $cfKey = trim((string) ($cf['key'] ?? ''));
            $cfType = trim((string) ($cf['type'] ?? ''));

            if ($cfKey === '') {
                continue;
            }

            $fieldLs = is_array($cf['language_setting'] ?? null) ? $cf['language_setting'] : [];

            $fieldLabelEng = trim((string) ($fieldLs['eng']['label'] ?? ''));
            $fieldLabelJpn = trim((string) ($fieldLs['jpn']['label'] ?? ''));
            $fieldDescEng = trim((string) ($fieldLs['eng']['description'] ?? ''));
            $fieldDescJpn = trim((string) ($fieldLs['jpn']['description'] ?? ''));

            $fieldLabelEngNorm = $fieldLabelEng !== '' ? $fieldLabelEng : $fieldLabelJpn;
            $fieldLabelJpnNorm = $fieldLabelJpn !== '' ? $fieldLabelJpn : $fieldLabelEng;
            $fieldDescEngNorm = $fieldDescEng !== '' ? $fieldDescEng : $fieldDescJpn;
            $fieldDescJpnNorm = $fieldDescJpn !== '' ? $fieldDescJpn : $fieldDescEng;

            $fieldLanguageSettingJson = $this->buildLanguageSettingJson(
                $fieldLabelEngNorm,
                $fieldLabelJpnNorm,
                $fieldDescEngNorm,
                $fieldDescJpnNorm
            );

            $labelEng = $fieldLabelEngNorm !== '' ? $fieldLabelEngNorm : ($cfType !== '' ? $cfType : $cfKey);
            $labelJpn = $fieldLabelJpnNorm !== '' ? $fieldLabelJpnNorm : $labelEng;

            $rows[] = [
                'field_key' => $cfKey,
                'option_value' => self::SINGLE_OPTION_VALUE,
                'label_eng' => $labelEng,
                'label_jpn' => $labelJpn,
                'language_setting' => $fieldLanguageSettingJson,
                'sort_order' => 0,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'deleted_at' => null,
            ];
        }

        return $rows;
    }

    private function buildLanguageSettingJson(
        string $labelEng,
        string $labelJpn,
        string $descEng = '',
        string $descJpn = ''
    ): string {
        $payload = [
            'eng' => [
                'label' => $labelEng,
                'description' => $descEng,
            ],
            'jpn' => [
                'label' => $labelJpn,
                'description' => $descJpn,
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '{"eng":{"label":"'.addslashes($labelEng).'","description":""},"jpn":{"label":"'.addslashes($labelJpn).'","description":""}}';
        }

        return $json;
    }

    /**
     * @param  array<int, array<string, mixed>>  $buffer
     */
    private function flush(array $buffer): int
    {
        $rowsByFieldKey = [];
        foreach ($buffer as $row) {
            $fieldKey = trim((string) ($row['field_key'] ?? ''));
            if ($fieldKey === '') {
                continue;
            }

            $rowsByFieldKey[$fieldKey] = $row;
        }

        foreach ($rowsByFieldKey as $row) {
            DB::table(self::TARGET_TABLE)->updateOrInsert(
                ['field_key' => (string) $row['field_key']],
                [
                    'option_value' => (string) ($row['option_value'] ?? self::SINGLE_OPTION_VALUE),
                    'label_eng' => (string) ($row['label_eng'] ?? ''),
                    'label_jpn' => (string) ($row['label_jpn'] ?? ''),
                    'language_setting' => (string) ($row['language_setting'] ?? ''),
                    'sort_order' => 0,
                    'created_at' => (string) ($row['created_at'] ?? now()->format('Y-m-d H:i:s')),
                    'updated_at' => (string) ($row['updated_at'] ?? now()->format('Y-m-d H:i:s')),
                    'deleted_at' => null,
                ]
            );
        }

        return count($rowsByFieldKey);
    }

    private function hardDeleteDuplicateRowsByFieldKey(): int
    {
        $keepIds = DB::table(self::TARGET_TABLE)
            ->selectRaw('MIN(id) as id')
            ->groupBy('field_key')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();

        if (empty($keepIds)) {
            return 0;
        }

        return DB::table(self::TARGET_TABLE)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }

        $v = str_replace(',', '', $v);

        if (! is_numeric($v)) {
            return null;
        }

        return (int) $v;
    }

    private function toBoolInt(mixed $value): int
    {
        $v = trim((string) $value);
        if ($v === '') {
            return 0;
        }

        if ($v === '1' || strcasecmp($v, 'true') === 0 || strcasecmp($v, 'yes') === 0) {
            return 1;
        }

        return 0;
    }

    private function toNullableDateTimeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }

        $v = str_replace(',', '', $v);

        $ts = strtotime($v);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }
}
