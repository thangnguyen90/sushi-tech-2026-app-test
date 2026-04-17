<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\MatchingUserRepository;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Exception;
use League\Csv\Reader;
use Throwable;

final class ImportMatchingUsersBackfillCommand extends Command implements ShouldBeUnique, ShouldQueue
{
    use CsvTrait;

    protected $signature = 'exhibitor_administrator_appointment_schedule_details
        {file : The CSV/TSV file path to import (on the selected disk)}
        {--disk=s3 : Storage disk name (e.g. s3, local)}
        {--chunk=500 : Number of rows to process per batch}';

    protected $description = 'Backfill matching_users from exhibitor_administrator_appointment_schedule_details CSV/TSV export.';

    public const string DISK = 's3';

    /**
     * Storage disk instance (set at runtime by CsvTrait usage).
     *
     * @var mixed
     */
    protected $disk;

    public function __construct(
        private readonly MatchingUserRepository $matchingUserRepository,
    ) {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    public function handle(): int
    {
        Log::info('ImportMatchingUsersBackfillCommand started');

        $filePath = (string) $this->argument('file');
        $diskName = (string) ($this->option('disk') ?: self::DISK);
        $chunkSize = (int) ($this->option('chunk') ?? 500);

        if ($chunkSize < 1) {
            $this->error('--chunk must be >= 1');

            return self::INVALID;
        }

        $this->disk = Storage::disk($diskName);

        $this->info('Import exhibitor_administrator_appointment_schedule_details');
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

            $buffer = [];
            $imported = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($csv->getRecords() as $index => $row) {
                $buffer[] = [
                    'line' => (int) $index + 2,
                    'row' => $row,
                ];

                if (count($buffer) >= $chunkSize) {
                    ['imported' => $batchImported, 'skipped' => $batchSkipped, 'failed' => $batchFailed] = $this->flush($buffer);

                    $imported += $batchImported;
                    $skipped += $batchSkipped;
                    $failed += $batchFailed;
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                ['imported' => $batchImported, 'skipped' => $batchSkipped, 'failed' => $batchFailed] = $this->flush($buffer);

                $imported += $batchImported;
                $skipped += $batchSkipped;
                $failed += $batchFailed;
            }

            $this->info("Backfill completed successfully. imported={$imported}, skipped={$skipped}, failed={$failed}");

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
     * @param  array<int, array{line: int, row: array<string, mixed>}>  $buffer
     * @return array{imported: int, skipped: int, failed: int}
     */
    private function flush(array $buffer): array
    {
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($buffer as $item) {
            try {
                $didImport = DB::transaction(
                    fn (): bool => $this->matchingUserRepository->addOrUpdateDataFromCsvRow($item['row'])
                );

                if ($didImport) {
                    $imported++;

                    continue;
                }

                $skipped++;
                $this->warn("Line {$item['line']}: not eligible for backfill => skipped");
            } catch (Throwable $throwable) {
                $failed++;

                $this->warn(sprintf(
                    'Line %d: failed to backfill appointment id=%s reason=%s',
                    $item['line'],
                    (string) ($item['row']['id'] ?? 'n/a'),
                    $throwable->getMessage()
                ));
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }
}
