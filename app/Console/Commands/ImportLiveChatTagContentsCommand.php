<?php

namespace App\Console\Commands;

use App\Repositories\LiveChatTagContentRepository;
use Illuminate\Console\Command;
use League\Csv\Exception;
use League\Csv\Reader;
use Illuminate\Support\Facades\Storage;

class ImportLiveChatTagContentsCommand extends Command
{
    use CsvTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-live-chat-tag-contents {file : The CSV file to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import live chat tag contents from CSV';

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

        $repository = app(LiveChatTagContentRepository::class);

        foreach ($csv->getRecords() as $row) {
            $id = $this->toInt($row['id'] ?? null);

            $repository->updateOrCreate(['id' => $id], [
                'id' => $id,
                'live_chat_tag_id' => $this->toInt($row['live_chat_tag_id'] ?? null),
                'name' => $row['name'] ?? null,
                'language_id' => $this->toInt($row['language_id'] ?? null),
                'is_publish' => filter_var($row['is_publish'] ?? null, FILTER_VALIDATE_BOOLEAN),
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
