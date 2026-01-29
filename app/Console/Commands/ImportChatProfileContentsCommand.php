<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Exception;
use League\Csv\Reader;
use Throwable;

final class ImportChatProfileContentsCommand extends Command
{
    use CsvTrait;

    /**
     * Example:
     * php artisan chat_profile_contents import/query_result_2026-01-29T05_41_24.1471966Z.csv --disk=local
     * php artisan chat_profile_contents import/query_result_2026-01-29T05_41_24.1471966Z.csv.gz --disk=local
     *
     * NOTE:
     * - The exported file is often TSV (tab-separated), not CSV.
     * - Supported input formats:
     *   (A) Legacy export: columns id, content_id, is_publish, type, json, created_at, updated_at
     *       -> json contains schema with custom_fields[].options[]
     *   (B) Direct export: field_key, option_value, label_eng, label_jpn, sort_order, is_enabled, created_at, updated_at
     *
     * @var string
     */
    protected $signature = 'chat_profile_contents
        {file : The CSV/TSV file path to import (on the selected disk)}
        {--disk=s3 : Storage disk name (e.g. s3, local)}
        {--chunk=500 : Number of rows per batch upsert}';

    /**
     * @var string
     */
    protected $description = 'Import data into chat_profile_contents table from CSV/TSV. Upsert by (field_key, option_value).';

    public const string DISK = 's3';

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
            // Read file from disk
            $file = $this->getFileContent($filePath);

            // Decompress only when file ends with .gz
            if (str_ends_with($filePath, '.gz')) {
                $file = $this->decompressGzip($file);
            }

            // Auto detect delimiter (TSV is common for your export)
            $delimiter = $this->detectDelimiter($file);

            // Parse CSV/TSV
            $csv = Reader::fromString($file)
                ->setDelimiter($delimiter)
                ->skipEmptyRecords()
                ->setHeaderOffset(0);

            $now = new DateTimeImmutable();

            $buffer = [];
            $imported = 0;
            $skipped = 0;

            foreach ($csv->getRecords() as $i => $row) {
                $lineNo = (int) $i + 2; // header is line 1

                $mappedRows = $this->mapRowToMany($row, $now, $lineNo);
                if (empty($mappedRows)) {
                    $skipped++;
                    continue;
                }

                foreach ($mappedRows as $r) {
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

            $this->info("Import completed successfully. imported={$imported}, skipped={$skipped}");
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
        $commaCount = substr_count($firstLine, ",");

        return $tabCount > $commaCount ? "\t" : ",";
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
        $optionValue = trim((string) ($row['option_value'] ?? $row['optionValue'] ?? ''));

        if ($fieldKey !== '' && $optionValue !== '') {
            $labelEng = trim((string) ($row['label_eng'] ?? $row['labelEng'] ?? ''));
            $labelJpn = trim((string) ($row['label_jpn'] ?? $row['labelJpn'] ?? ''));

            if ($labelEng === '' || $labelJpn === '') {
                $this->warn("Line {$lineNo}: missing label_eng/label_jpn => skipped");
                return [];
            }

            $sortOrder = $this->toNullableInt($row['sort_order'] ?? $row['sortOrder'] ?? null) ?? 0;
            $isEnabled = $this->toBoolInt($row['is_enabled'] ?? $row['isEnabled'] ?? 1);

            $createdAt = $this->toNullableDateTimeString($row['created_at'] ?? null) ?? $now->format('Y-m-d H:i:s');
            $updatedAt = $this->toNullableDateTimeString($row['updated_at'] ?? null) ?? $now->format('Y-m-d H:i:s');

            return [[
                'field_key' => $fieldKey,
                'option_value' => $optionValue,
                'label_eng' => $labelEng,
                'label_jpn' => $labelJpn,
                'sort_order' => (int) $sortOrder,
                'is_enabled' => (int) $isEnabled,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]];
        }

        // Case 2: legacy format (json column)
        $jsonStr = trim((string) ($row['json'] ?? ''));
        if ($jsonStr === '') {
            $this->warn("Line {$lineNo}: missing field_key/option_value and missing json => skipped");
            return [];
        }

        $decoded = json_decode($jsonStr, true);
        if (!is_array($decoded)) {
            $this->warn("Line {$lineNo}: invalid json => skipped. error=" . json_last_error_msg());
            return [];
        }

        $createdAt = $this->toNullableDateTimeString($row['created_at'] ?? null) ?? $now->format('Y-m-d H:i:s');
        $updatedAt = $this->toNullableDateTimeString($row['updated_at'] ?? null) ?? $now->format('Y-m-d H:i:s');

        $rows = $this->extractRowsFromSchemaJson($decoded, $createdAt, $updatedAt);

        if (empty($rows)) {
            $this->warn("Line {$lineNo}: json parsed but no selectable options found => skipped");
        }

        return $rows;
    }

    /**
     * Extract rows for chat_profile_contents from schema JSON:
     * - custom_fields[] where type == select and is_enabled == true
     * - options[] => option_value + labels
     *
     * @param array<string, mixed> $decoded
     * @return array<int, array<string, mixed>>
     */
    private function extractRowsFromSchemaJson(array $decoded, string $createdAt, string $updatedAt): array
    {
        $rows = [];

        $customFields = $decoded['custom_fields'] ?? null;
        if (!is_array($customFields)) {
            return [];
        }

        foreach ($customFields as $cf) {
            if (!is_array($cf)) {
                continue;
            }

            $cfKey = trim((string) ($cf['key'] ?? ''));
            $cfType = trim((string) ($cf['type'] ?? ''));
            $cfEnabled = (bool) ($cf['is_enabled'] ?? true);

            if ($cfKey === '' || $cfType !== 'select' || !$cfEnabled) {
                continue;
            }

            $options = $cf['options'] ?? null;
            if (!is_array($options)) {
                continue;
            }

            $sortOrder = 0;

            foreach ($options as $opt) {
                if (!is_array($opt)) {
                    continue;
                }

                $optionValue = trim((string) ($opt['value'] ?? ''));
                if ($optionValue === '') {
                    continue;
                }

                $ls = $opt['language_setting'] ?? [];
                $labelEng = trim((string) (($ls['eng']['label'] ?? '') ?: ''));
                $labelJpn = trim((string) (($ls['jpn']['label'] ?? '') ?: ''));

                // fallback if only one label exists
                if ($labelEng === '' && $labelJpn !== '') {
                    $labelEng = $labelJpn;
                }
                if ($labelJpn === '' && $labelEng !== '') {
                    $labelJpn = $labelEng;
                }

                if ($labelEng === '' || $labelJpn === '') {
                    continue;
                }

                $sortOrder++;

                $rows[] = [
                    'field_key' => $cfKey,
                    'option_value' => $optionValue,
                    'label_eng' => $labelEng,
                    'label_jpn' => $labelJpn,
                    'sort_order' => $sortOrder,
                    'is_enabled' => 1,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];
            }
        }

        return $rows;
    }

    /**
     * Upsert buffered rows to DB (unique key (field_key, option_value)).
     *
     * @param array<int, array<string, mixed>> $buffer
     */
    private function flush(array $buffer): int
    {
        DB::table('chat_profile_contents')->upsert(
            $buffer,
            ['field_key', 'option_value'],
            ['label_eng', 'label_jpn', 'sort_order', 'is_enabled', 'updated_at']
        );

        return count($buffer);
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

        // handle values like "9,965,895"
        $v = str_replace(',', '', $v);

        if (!is_numeric($v)) {
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

        // Try parse strings like "1-29-2026, 12:40"
        $v = str_replace(',', '', $v);

        $ts = strtotime($v);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }
}
