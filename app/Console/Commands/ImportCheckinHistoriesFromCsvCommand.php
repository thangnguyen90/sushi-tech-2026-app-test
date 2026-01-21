<?php

namespace App\Console\Commands;

use App\Repositories\CheckinHistoryRepository;
use Illuminate\Console\Command;
use League\Csv\Exception;
use League\Csv\Reader;
use Illuminate\Support\Facades\Storage;

class ImportCheckinHistoriesFromCsvCommand extends Command
{
    use CsvTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-checkin-histories {file : The CSV file to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import checkin histories from CSV';

    const string DISK = 'local';

    /**
     * Execute the console command.
     * @throws Exception
     */
    public function handle(): void
    {
        $file = $this->argument('file');
        $this->disk = Storage::disk(self::DISK);
        $file = $this->getFileContent($file);
        // $file = $this->decompressGzip($file);
        $csv = Reader::fromString($file)->skipEmptyRecords()->setHeaderOffset(0);

        $repository = app(CheckinHistoryRepository::class);

        foreach ($csv->getRecords() as $row) {
            $attributes = [
                'exhibitor_administrator_id' => $this->toInt($row['exhibitor_administrator_id'] ?? null),
                'user_id' => $this->toInt($row['user_id'] ?? null),
                'event_id' => $this->toInt($row['event_id'] ?? null),
                'checkin_app_user_name' => $row['checkin_app_user_name'] ?? null,
            ];

            $repository->updateOrCreate($attributes, [
                'exhibitor_administrator_id' => $this->toInt($row['exhibitor_administrator_id'] ?? null),
                'user_id' => $this->toInt($row['user_id'] ?? null),
                'event_id' => $this->toInt($row['event_id'] ?? null),
                'checkin_app_user_name' => $row['checkin_app_user_name'] ?? null,
                'is_confirm' => $this->toInt($row['is_confirm'] ?? null),
            ]);
        }

        $this->info('Import completed successfully.');
    }

    private function toInt(mixed $value): int
    {
        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        return (int) (is_numeric($value) ? $value : 0);
    }
}
