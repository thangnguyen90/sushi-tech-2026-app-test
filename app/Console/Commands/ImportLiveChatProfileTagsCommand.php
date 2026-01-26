<?php

namespace App\Console\Commands;

use App\Repositories\LiveChatProfileTagRepository;
use Illuminate\Console\Command;
use League\Csv\Exception;
use League\Csv\Reader;
use Illuminate\Support\Facades\Storage;

class ImportLiveChatProfileTagsCommand extends Command
{
    use CsvTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live_chat_profile_tags {file : The CSV file to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import live chat profile tags from CSV';

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

        $repository = app(LiveChatProfileTagRepository::class);

        foreach ($csv->getRecords() as $row) {

            $profileId = (int) (is_numeric($row['user_live_chat_profile_id']) ? $row['user_live_chat_profile_id'] : 1111);
            $dataSourceId = (int) (is_numeric($row['live_chat_data_source_id']) ? $row['live_chat_data_source_id'] : 0);
            $tags = $this->parseTags($row['tags_json'] ?? []);
            $attributes = [
                'user_live_chat_profile_id' => $profileId,
                'live_chat_data_source_id' => $dataSourceId,
            ];
            $languageId = (int) $row['language_id'];

            $data = $repository->updateOrCreate(
                $attributes,
                [
                    'user_live_chat_profile_id' => $profileId,
                    'live_chat_data_source_id'  => $dataSourceId,
                ]
            );

            // đảm bảo tags là array
            $currentTags = is_array($data->tags) ? $data->tags : (array) ($data->tags ?? []);

            // merge theo languageId (ghi đè languageId đó)
            $newTags = $currentTags;
            $newTags[$languageId] = $tags;

            // chỉ save nếu có thay đổi
            if ($newTags !== $currentTags) {
                $data->tags = $newTags;
                $data->save();

                $this->info("Upserted tags for profile ID: {$profileId}, data source ID: {$dataSourceId}, language ID: {$languageId}");
            } else {
                $this->info("No tag changes for profile ID: {$profileId}, data source ID: {$dataSourceId}, language ID: {$languageId}");
            }
        }

        $this->info('Import completed successfully.');
    }

    private function parseTags(mixed $value): array
    {
        if (is_array($value)) {
            $tags = $value;
        } elseif (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                $tags = [];
            } else {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $tags = $decoded;
                } else {
                    $tags = array_map('trim', explode(',', $value));
                }
            }
        } else {
            $tags = [];
        }

        return $tags;
    }
}
