<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use League\Csv\Exception;
use League\Csv\Reader;
use App\Repositories\LiveChatProfilesRepository;
use \Illuminate\Support\Facades\Storage;

class ImportLiveChatProfile extends Command
{
    use CsvTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live_chat_profiles {file : The CSV file to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    const string DISK = 's3';

    /**
     * Execute the console command.
     * @throws Exception
     */
    public function handle()
    {
        $file = $this->argument('file');
        $this->disk = Storage::disk(self::DISK);
        $file = $this->getFileContent($file);
        $file = $this->decompressGzip($file);
        $csv = Reader::fromString($file)->skipEmptyRecords()->setHeaderOffset(0);

        $repository = app(LiveChatProfilesRepository::class);

        foreach ($csv->getRecords() as $row) {
            $attributes = [
                'uuid' => $row['uuid']  ?? null,
                'user_id' => (int)(is_numeric($row['user_id']) ? $row['user_id'] : 0),
                'live_chat_data_source_id' => (int)(is_numeric($row['live_chat_data_source_id'] ?? null) ? $row['live_chat_data_source_id'] : 0),
                'live_chat_user_id' => (int)(is_numeric($row['live_chat_user_id'] ?? null) ? $row['live_chat_user_id'] : 0),
            ];
            $repository->updateOrCreate($attributes, [
                'user_id' => (int)(is_numeric($row['user_id']) ? $row['user_id'] : 0) ,
                'live_chat_data_source_id' => (int)(is_numeric($row['live_chat_data_source_id'] ) ? $row['live_chat_data_source_id'] : 0),
                'live_chat_user_id' => (int)(is_numeric($row['live_chat_user_id'] ) ? $row['live_chat_user_id'] : 0),
                'uuid' => $row['uuid'] ?? null,
                'nickname' => $row['nickname'] ?? null,
                'icon_image' => $row['icon_image'] ?? null,
                'background_image' => $row['background_image'] ?? null,
                'introduction' => $row['introduction'] ?? null,
//                'mail_notification' => $row['mail_notification'] ?? null,
                'mail_address' => $row['mail_address'] ?? null,
                'company' => $row['company'] ?? null,
                'custom_fields' => $row['custom_fields'] ??[],
//                'display_is_search' => $row['display_is_search'] ?? null,
                'exhibitor_administrator_id' => (int)(is_numeric($row['exhibitor_administrator_id'] ?? null) ? $row['exhibitor_administrator_id'] : 1),
                'last_portal_id' => (int)(is_numeric($row['last_portal_id'] ) ? $row['last_portal_id'] : 0),
                'last_event_id' => (int)(is_numeric($row['last_event_id'] ) ? $row['last_event_id'] : 0),
            ]);
        }

        $this->info('Import completed successfully.');
    }
}
