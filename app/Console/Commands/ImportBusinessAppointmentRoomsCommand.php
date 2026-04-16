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

final class ImportBusinessAppointmentRoomsCommand extends Command implements ShouldBeUnique, ShouldQueue
{
    use CsvTrait;

    protected $signature = 'business_appointment_rooms
        {file : The CSV/TSV file path to import (on the selected disk)}
        {--disk=s3 : Storage disk name (e.g. s3, local)}
        {--chunk=500 : Number of rows per batch upsert}';

    protected $description = 'Import data into business_appointment_rooms table from CSV/TSV. Upsert by (business_appointment_room_id, language_id).';

    public const string DISK = 's3';

    private const string TARGET_TABLE = 'business_appointment_rooms';

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
        Log::info('ImportBusinessAppointmentRoomsCommand started');
        $filePath = (string) $this->argument('file');
        $diskName = (string) ($this->option('disk') ?: self::DISK);
        $chunkSize = (int) ($this->option('chunk') ?? 500);

        if ($chunkSize < 1) {
            $this->error('--chunk must be >= 1');

            return self::INVALID;
        }

        $this->disk = Storage::disk($diskName);

        $this->info('Import business_appointment_rooms');
        $this->line("Disk: {$diskName}");
        $this->line("File: {$filePath}");
        $this->line("Chunk: {$chunkSize}");

        try {
            $file = $this->getFileContent($filePath);

            if (str_ends_with($filePath, '.gz')) {
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
        $roomId = $this->toNullableInt($row['business_appointment_room_id'] ?? null);
        $languageId = $this->toNullableInt($row['language_id'] ?? null);
        $name = trim((string) ($row['name'] ?? ''));

        if ($roomId === null || $languageId === null || $name === '') {
            $this->warn("Line {$lineNo}: missing required columns => skipped");

            return null;
        }

        return [
            'business_appointment_room_id' => $roomId,
            'content_id' => $this->toNullableInt($row['content_id'] ?? null),
            'name' => $name,
            'is_free' => $this->toNullableInt($row['is_free'] ?? null) === 1,
            'room_image' => $this->normalizeJson($row['room_image'] ?? null, $lineNo),
            'language_id' => $languageId,
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

    private function normalizeJson(mixed $value, int $lineNo): ?string
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
            $this->warn("Line {$lineNo}: invalid room_image json => storing null");

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
            ['business_appointment_room_id', 'language_id'],
            ['content_id', 'name', 'is_free', 'room_image', 'updated_at', 'deleted_at']
        );

        return count($buffer);
    }
}
