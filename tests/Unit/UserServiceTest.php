<?php

namespace Tests\Unit;

use App\Services\Eventos\User\UserService;
use Mockery;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    public function test_get_users_list_aggregates_all_pages(): void
    {
        $service = Mockery::mock(UserService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('requestUsersListPage')
            ->once()
            ->with(1)
            ->andReturn([
                'data' => [
                    ['user_id' => 101, 'user_qrcode' => 'QR-101'],
                ],
                'current_page' => 1,
                'last_page' => 2,
            ]);

        $service->shouldReceive('requestUsersListPage')
            ->once()
            ->with(2)
            ->andReturn([
                'data' => [
                    ['user_id' => 202, 'user_qrcode' => 'QR-202'],
                ],
                'current_page' => 2,
                'last_page' => 2,
            ]);

        $result = $service->getUsersList(0);

        $this->assertSame([
            ['user_id' => 101, 'user_qrcode' => 'QR-101'],
            ['user_id' => 202, 'user_qrcode' => 'QR-202'],
        ], $result['data']);
    }

    public function test_get_users_list_aggregates_all_pages_with_total_page_and_per_page_format(): void
    {
        $service = Mockery::mock(UserService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('requestUsersListPage')
            ->once()
            ->with(1)
            ->andReturn([
                'total' => 50,
                'page' => 1,
                'per_page' => 25,
                'data' => [
                    ['user_id' => 101, 'user_qrcode' => 'QR-101'],
                ],
            ]);

        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('requestUsersListPage')
            ->once()
            ->with(2)
            ->andReturn([
                'total' => 50,
                'page' => 2,
                'per_page' => 25,
                'data' => [
                    ['user_id' => 202, 'user_qrcode' => 'QR-202'],
                ],
            ]);

        $result = $service->getUsersList(0);

        $this->assertSame([
            ['user_id' => 101, 'user_qrcode' => 'QR-101'],
            ['user_id' => 202, 'user_qrcode' => 'QR-202'],
        ], $result['data']);
    }

    public function test_get_users_list_uses_next_page_url_when_last_page_is_missing(): void
    {
        $service = Mockery::mock(UserService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('requestUsersListPage')
            ->once()
            ->with(1)
            ->andReturn([
                'data' => [
                    ['user_id' => 101, 'user_qrcode' => 'QR-101'],
                ],
                'next_page_url' => 'https://public-api.example.test/api/v1/user/list?page=2',
            ]);

        $service->shouldReceive('requestUsersListPage')
            ->once()
            ->with(2)
            ->andReturn([
                'data' => [
                    ['user_id' => 202, 'user_qrcode' => 'QR-202'],
                ],
                'next_page_url' => null,
            ]);

        $result = $service->getUsersList(0);

        $this->assertCount(2, $result['data']);
        $this->assertSame(202, $result['data'][1]['user_id']);
    }
}
