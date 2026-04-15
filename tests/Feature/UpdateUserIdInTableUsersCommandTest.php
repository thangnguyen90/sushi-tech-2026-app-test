<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Eventos\User\UserService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateUserIdInTableUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_missing_user_ids_by_matching_user_qrcode(): void
    {
        $firstUser = User::query()->create([
            'uuid' => 'user-uuid-1',
        ]);
        $secondUser = User::query()->create([
            'uuid' => 'user-uuid-2',
        ]);
        $unchangedUser = User::query()->create([
            'uuid' => 'user-uuid-3',
            'user_id' => 999,
        ]);

        $this->mock(UserService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forEachUserListPage')
                ->once()
                ->andReturnUsing(function (callable $pageProcessor): void {
                    $pageProcessor([
                        'total' => 2,
                        'page' => 1,
                        'per_page' => 25,
                        'data' => [
                            ['user_id' => 101, 'user_qrcode' => 'QR-101'],
                            ['user_id' => 202, 'user_qrcode' => 'QR-202'],
                        ],
                    ], 1);
                });

            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('user-uuid-1', 0)
                ->andReturn([
                    'data' => [
                        'user_qrcode' => 'QR-101',
                    ],
                ]);

            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('user-uuid-2', 0)
                ->andReturn([
                    'data' => [
                        'user_qrcode' => 'QR-202',
                    ],
                ]);
        });

        $this->artisan('app:update-user-id-in-table-users')
            ->expectsOutput('Processed 2 users. Updated=2 SkippedMissingQr=0 SkippedMissingMatch=0 Failed=0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'id' => $firstUser->id,
            'user_id' => 101,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $secondUser->id,
            'user_id' => 202,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $unchangedUser->id,
            'user_id' => 999,
        ]);
    }

    public function test_it_skips_users_when_user_qrcode_cannot_be_resolved(): void
    {
        $missingQrCodeUser = User::query()->create([
            'uuid' => 'user-without-qr',
        ]);
        $missingMatchUser = User::query()->create([
            'uuid' => 'user-without-match',
        ]);

        $this->mock(UserService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forEachUserListPage')
                ->once()
                ->andReturnUsing(function (callable $pageProcessor): void {
                    $pageProcessor([
                        'total' => 1,
                        'page' => 1,
                        'per_page' => 25,
                        'data' => [
                            ['user_id' => 101, 'user_qrcode' => 'QR-101'],
                        ],
                    ], 1);
                });

            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('user-without-qr', 0)
                ->andReturn([
                    'data' => [],
                ]);

            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('user-without-match', 0)
                ->andReturn([
                    'data' => [
                        'user_qrcode' => 'QR-999',
                    ],
                ]);
        });

        $this->artisan('app:update-user-id-in-table-users')
            ->expectsOutput('Processed 2 users. Updated=0 SkippedMissingQr=1 SkippedMissingMatch=1 Failed=0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'id' => $missingQrCodeUser->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $missingMatchUser->id,
            'user_id' => null,
        ]);
    }

    public function test_it_continues_when_a_user_detail_request_fails(): void
    {
        $failedUser = User::query()->create([
            'uuid' => 'failed-user',
        ]);
        $updatedUser = User::query()->create([
            'uuid' => 'updated-user',
        ]);

        $this->mock(UserService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forEachUserListPage')
                ->once()
                ->andReturnUsing(function (callable $pageProcessor): void {
                    $pageProcessor([
                        'total' => 1,
                        'page' => 1,
                        'per_page' => 25,
                        'data' => [
                            ['user_id' => 303, 'user_qrcode' => 'QR-303'],
                        ],
                    ], 1);
                });

            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('failed-user', 0)
                ->andThrow(new Exception('Eventos error'));

            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('updated-user', 0)
                ->andReturn([
                    'data' => [
                        'user_qrcode' => 'QR-303',
                    ],
                ]);
        });

        $this->artisan('app:update-user-id-in-table-users')
            ->expectsOutput('Processed 2 users. Updated=1 SkippedMissingQr=0 SkippedMissingMatch=0 Failed=1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'id' => $failedUser->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $updatedUser->id,
            'user_id' => 303,
        ]);
    }
}
