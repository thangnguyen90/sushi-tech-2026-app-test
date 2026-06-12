<?php

namespace Tests\Feature;

use App\Models\ShareProfileField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportShareProfileFieldsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_and_updates_share_profile_fields_from_local_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/share_profile_fields.csv', $this->csvContent([
            [
                'id' => '72861',
                'share_profile_id' => '14635',
                'language_id' => '1',
                'is_enabled' => 'true',
                'is_required' => 'true',
                'is_uneditable' => 'false',
                'is_hidden' => 'false',
                'is_default' => 'false',
                'label' => '参加属性',
                'entry_form_key' => '2c0e209c-f167-4906-85fd-51204922e7dc',
                'answer_method' => 'SELECT',
                'priority' => '11',
                'setting' => '{"place_holder": ""}',
                'selector_items' => '[{"key":"a","value":"スタートアップ"}]',
                'created_at' => '2025-2-5, 13:31',
                'updated_at' => '2026-4-13, 21:18',
                'deleted_at' => '',
            ],
            [
                'id' => '72864',
                'share_profile_id' => '14635',
                'language_id' => '2',
                'is_enabled' => 'true',
                'is_required' => 'true',
                'is_uneditable' => 'false',
                'is_hidden' => 'false',
                'is_default' => 'false',
                'label' => 'Participation Attributes',
                'entry_form_key' => '2c0e209c-f167-4906-85fd-51204922e7dc',
                'answer_method' => 'SELECT',
                'priority' => '11',
                'setting' => '{"place_holder": ""}',
                'selector_items' => '[{"key":"a","value":"Startup"}]',
                'created_at' => '2025-2-5, 13:31',
                'updated_at' => '2026-4-13, 21:18',
                'deleted_at' => '',
            ],
        ]));

        $this->artisan('share_profile_fields', [
            'file' => 'imports/share_profile_fields.csv',
            '--disk' => 'local',
            '--chunk' => 1,
        ])->assertExitCode(0);

        $this->assertSame(2, ShareProfileField::count());
        $this->assertDatabaseHas('share_profile_contents', [
            'id' => 72861,
            'share_profile_id' => 14635,
            'language_id' => 1,
            'label' => '参加属性',
            'answer_method' => 'SELECT',
            'priority' => 11,
            'created_at' => '2025-02-05 13:31:00',
            'updated_at' => '2026-04-13 21:18:00',
        ]);

        $field = ShareProfileField::query()->findOrFail(72861);

        $this->assertTrue($field->is_enabled);
        $this->assertFalse($field->is_uneditable);
        $this->assertSame('', $field->setting['place_holder']);
        $this->assertSame('スタートアップ', $field->selector_items[0]['value']);

        Storage::disk('local')->put('imports/share_profile_fields.csv', $this->csvContent([
            [
                'id' => '72861',
                'share_profile_id' => '14635',
                'language_id' => '1',
                'is_enabled' => 'true',
                'is_required' => 'true',
                'is_uneditable' => 'true',
                'is_hidden' => 'false',
                'is_default' => 'true',
                'label' => '参加属性更新',
                'entry_form_key' => '2c0e209c-f167-4906-85fd-51204922e7dc',
                'answer_method' => 'SELECT',
                'priority' => '15',
                'setting' => '{"place_holder": "updated"}',
                'selector_items' => '[{"key":"a","value":"スタートアップ"},{"key":"b","value":"その他"}]',
                'created_at' => '2025-2-5, 13:31',
                'updated_at' => '2026-4-20, 08:05',
                'deleted_at' => '',
            ],
        ]));

        $this->artisan('share_profile_fields', [
            'file' => 'imports/share_profile_fields.csv',
            '--disk' => 'local',
        ])->assertExitCode(0);

        $updatedField = ShareProfileField::query()->findOrFail(72861);

        $this->assertSame('参加属性更新', $updatedField->label);
        $this->assertTrue($updatedField->is_uneditable);
        $this->assertTrue($updatedField->is_default);
        $this->assertSame('updated', $updatedField->setting['place_holder']);
        $this->assertSame('2026-04-20 08:05:00', $updatedField->getRawOriginal('updated_at'));
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function csvContent(array $rows): string
    {
        $headers = [
            'id',
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
        ];

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['id'],
                $row['share_profile_id'],
                $row['language_id'],
                $row['is_enabled'],
                $row['is_required'],
                $row['is_uneditable'],
                $row['is_hidden'],
                $row['is_default'],
                $row['label'],
                $row['entry_form_key'],
                $row['answer_method'],
                $row['priority'],
                $row['setting'],
                $row['selector_items'],
                $row['created_at'],
                $row['updated_at'],
                $row['deleted_at'],
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }
}
