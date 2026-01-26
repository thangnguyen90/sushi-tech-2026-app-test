<?php

namespace App\Console\Commands;

use App\Repositories\UserLiveChatTagRepository;
use Illuminate\Console\Command;
use League\Csv\Exception;
use League\Csv\Reader;
use Illuminate\Support\Facades\Storage;

class ImportUserLiveChatTagsFromCsvCommand extends Command
{
    use CsvTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user_live_chat_tags {file : The CSV file to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import user live chat tags from CSV';

    const string DISK = 's3';

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

        $repository = app(UserLiveChatTagRepository::class);

        foreach ($csv->getRecords() as $row) {
            $id = $this->toInt($row['id'] ?? null);

            $repository->updateOrCreate(['id' => $id], [
                'id' => $id,
                'live_chat_data_source_id' => $this->toInt($row['live_chat_data_source_id'] ?? null),
                'user_live_chat_profile_id' => $this->toInt($row['user_live_chat_profile_id'] ?? null),
                'live_chat_tag_id' => $this->toInt($row['live_chat_tag_id'] ?? null),
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
