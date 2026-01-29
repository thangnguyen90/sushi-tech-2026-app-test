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
     * php artisan chat_profile_contents import/query_result_2026-01-29T04_15_54.374511847Z.csv --disk=local
     * php artisan chat_profile_contents import/query_result_2026-01-29T04_15_54.374511847Z.csv.gz --disk=local
     *
     * @var string
     */
    protected $signature = 'chat_profile_contents
        {file : The CSV file path to import (on the selected disk)}
        {--disk=s3 : Storage disk name (e.g. s3, local)}
        {--chunk=500 : Number of rows per batch upsert}';

    /**
     * @var string
     */
    protected $description = 'Import data into chat_profile_contents table from CSV (same read flow as ImportLiveChatProfile).';

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

            // Parse CSV
            $csv = Reader::fromString($file)
                ->skipEmptyRecords()
                ->setHeaderOffset(0);

            $now = new DateTimeImmutable();

            $buffer = [];
            $imported = 0;
            $skipped = 0;

            foreach ($csv->getRecords() as $i => $row) {
                $lineNo = (int) $i + 2; // header is line 1

                $mapped = $this->mapRow($row, $now, $lineNo);
                if ($mapped === null) {
                    $skipped++;
                    continue;
                }

                $buffer[] = $mapped;

                if (count($buffer) >= $chunkSize) {
                    $imported += $this->flush($buffer);
                    $buffer = [];
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

    /**
     * Map a CSV row => DB record array.
     *
     * Expected columns (minimum):
     * - id
     * - content_id
     * - is_publish
     * - type
     * - json
     * - created_at (optional)
     * - updated_at (optional)
     */
    private function mapRow(array $row, DateTimeImmutable $now, int $lineNo): ?array
    {
        $id = $this->toNullableInt($row['id'] ?? null);
        $contentId = $this->toNullableInt($row['content_id'] ?? null);

        if ($id === null || $contentId === null) {
            $this->warn("Line {$lineNo}: missing id/content_id => skipped");
            return null;
        }

        $type = trim((string) ($row['type'] ?? ''));
        if ($type === '') {
            $this->warn("Line {$lineNo}: missing type => skipped");
            return null;
        }

        $isPublish = $this->toBoolInt($row['is_publish'] ?? 0);

        $jsonStr = trim((string) ($row['json'] ?? '{}'));
        if ($jsonStr === '') {
            $jsonStr = '{}';
        }

        // Validate JSON to avoid inserting invalid data
        json_decode($jsonStr, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->warn("Line {$lineNo}: invalid json => skipped. error=" . json_last_error_msg());
            return null;
        }

        $createdAt = $this->toNullableDateTimeString($row['created_at'] ?? null) ?? $now->format('Y-m-d H:i:s');
        $updatedAt = $this->toNullableDateTimeString($row['updated_at'] ?? null) ?? $now->format('Y-m-d H:i:s');

        return [
            'id' => $id,
            'content_id' => $contentId,
            'is_publish' => $isPublish,
            'type' => $type,
            'json' => $jsonStr,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * Upsert buffered rows to DB (re-runnable by primary key id).
     *
     * @param array<int, array<string, mixed>> $buffer
     */
    private function flush(array $buffer): int
    {
        DB::table('chat_profile_contents')->upsert(
            $buffer,
            ['id'],
            ['content_id', 'is_publish', 'type', 'json', 'updated_at']
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

        // Handle values like "9,965,895"
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

        // Try parse strings like "1-29-2026, 12:40" or standard timestamps
        $v = str_replace(',', '', $v);

        $ts = strtotime($v);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }
}
