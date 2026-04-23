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
use RuntimeException;
use Throwable;

final class ImportShareProfileFieldsCommand extends Command implements ShouldBeUnique, ShouldQueue
{
    use CsvTrait;

    protected $signature = 'share_profile_contents
        {file : The CSV/TSV file path to import}
        {--disk=s3 : Storage disk name (e.g. s3, local)}
        {--chunk=500 : Number of rows per batch upsert}';

    protected $description = 'Import data into share_profile_contents table from CSV/TSV export.';

    public const string DISK = 's3';

    private const string TARGET_TABLE = 'share_profile_contents';

    /**
     * Storage disk instance (set at runtime by CsvTrait usage).
     *
     * @var mixed
     */
    protected $disk;

    /**
     * @throws Exception
     */
    public function handle(): int
    {
        Log::info('ImportShareProfileFieldsCommand started');

        $filePath = (string) $this->argument('file');
        $diskName = (string) ($this->option('disk') ?: self::DISK);
        $chunkSize = (int) ($this->option('chunk') ?? 500);

        if ($chunkSize < 1) {
            $this->error('--chunk must be >= 1');

            return self::INVALID;
        }

        $this->disk = Storage::disk($diskName);

        $this->info('Import share_profile_contents');
        $this->line("Disk: {$diskName}");
        $this->line("File: {$filePath}");
        $this->line("Chunk: {$chunkSize}");

        try {
            $file = $this->readInputFile($filePath);

            if (str_ends_with(strtolower($filePath), '.gz')) {
                $file = $this->decompressGzip($file);
            }

            $csv = Reader::fromString($file)
                ->setDelimiter($this->detectDelimiter($file))
                ->skipEmptyRecords()
                ->setHeaderOffset(0);

            $now = new DateTimeImmutable;
            $buffer = [];
            $imported = 0;
            $skipped = 0;

            foreach ($csv->getRecords() as $index => $row) {
                $mappedRow = $this->mapRow($row, $now, (int) $index + 2);

                if ($mappedRow === null) {
                    $skipped++;

                    continue;
                }

                $buffer[] = $mappedRow;

                if (count($buffer) >= $chunkSize) {
                    $imported += $this->flush($buffer);
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                $imported += $this->flush($buffer);
            }

            $this->info("Import completed successfully. imported={$imported}, skipped={$skipped}");

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }
    }

    private function readInputFile(string $filePath): string
    {
        if (is_file($filePath)) {
            $contents = file_get_contents($filePath);

            if ($contents === false) {
                throw new RuntimeException("Failed to read local file: {$filePath}");
            }

            return $contents;
        }

        return $this->getFileContent($filePath);
    }

    private function detectDelimiter(string $file): string
    {
        $firstLine = strtok($file, "\n") ?: '';
        $tabCount = substr_count($firstLine, "\t");
        $commaCount = substr_count($firstLine, ',');

        return $tabCount > $commaCount ? "\t" : ',';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function mapRow(array $row, DateTimeImmutable $now, int $lineNo): ?array
    {
        $id = $this->toNullableInt($row['id'] ?? null);
        $shareProfileId = $this->toNullableInt($row['share_profile_id'] ?? null);
        $languageId = $this->toNullableInt($row['language_id'] ?? null);

        if ($id === null || $shareProfileId === null || $languageId === null) {
            $this->warn("Line {$lineNo}: missing required columns => skipped");

            return null;
        }

        return [
            'id' => $id,
            'share_profile_id' => $shareProfileId,
            'language_id' => $languageId,
            'is_enabled' => $this->toNullableBool($row['is_enabled'] ?? null) ?? true,
            'is_required' => $this->toNullableBool($row['is_required'] ?? null) ?? true,
            'is_uneditable' => $this->toNullableBool($row['is_uneditable'] ?? null),
            'is_hidden' => $this->toNullableBool($row['is_hidden'] ?? null) ?? false,
            'is_default' => $this->toNullableBool($row['is_default'] ?? null) ?? false,
            'label' => $this->toNullableString($row['label'] ?? null),
            'entry_form_key' => $this->toNullableString($row['entry_form_key'] ?? null),
            'answer_method' => $this->toNullableString($row['answer_method'] ?? null),
            'priority' => $this->toNullableInt($row['priority'] ?? null),
            'setting' => $this->normalizeJson($row['setting'] ?? null, $lineNo, 'setting'),
            'selector_items' => $this->normalizeJson($row['selector_items'] ?? null, $lineNo, 'selector_items'),
            'created_at' => $this->toNullableDateTimeString($row['created_at'] ?? null) ?? $now->format('Y-m-d H:i:s'),
            'updated_at' => $this->toNullableDateTimeString($row['updated_at'] ?? null) ?? $now->format('Y-m-d H:i:s'),
            'deleted_at' => $this->toNullableDateTimeString($row['deleted_at'] ?? null),
        ];
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function toNullableBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            '' => null,
            default => null,
        };
    }

    private function toNullableDateTimeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = [
            'Y-n-j, G:i',
            'Y-n-j, H:i',
            'Y-m-d H:i:s',
            DATE_ATOM,
        ];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function normalizeJson(mixed $value, int $lineNo, string $column): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->warn("Line {$lineNo}: invalid {$column} json => storing null");

            return null;
        }

        $json = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? null : $json;
    }

    /**
     * @param  array<int, array<string, mixed>>  $buffer
     */
    private function flush(array $buffer): int
    {
        DB::table(self::TARGET_TABLE)->upsert(
            $buffer,
            ['id'],
            [
                'share_profile_id',
                'language_id',
                'is_enabled',
                'is_required',
                'is_uneditable',
                'is_hidden',
                'is_default',
                'label',
                'entry_form_key',
                'answer_method',
                'priority',
                'setting',
                'selector_items',
                'created_at',
                'updated_at',
                'deleted_at',
            ]
        );

        return count($buffer);
    }
}
