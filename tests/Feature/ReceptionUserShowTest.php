<?php

namespace Tests\Feature;

use App\Services\Eventos\User\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ReceptionUserShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_reception_user_information_for_a_scanned_uuid(): void
    {
        $this->mock(UserService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('11111111-1111-4111-8111-111111111111', 0)
                ->andReturn([
                    'account' => 'taro.event@example.com',
                    'user_qrcode' => '11111111-1111-4111-8111-111111111111',
                    'profiles' => [
                        'share_profiles' => [
                            [
                                'share_profile_id' => 1001,
                                'text_value' => null,
                                'selector_value' => [
                                    [
                                        'key' => 'last_name',
                                        'value' => '山田',
                                    ],
                                    [
                                        'key' => 'first_name',
                                        'value' => '太郎',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);
        });

        $response = $this->getJson('/api/v1/reception/users/11111111-1111-4111-8111-111111111111');

        $response->assertOk()
            ->assertJsonPath('code', 'OK')
            ->assertJsonPath('result.user_uuid', '11111111-1111-4111-8111-111111111111')
            ->assertJsonPath('result.user_id', null)
            ->assertJsonPath('result.name', '山田 太郎')
            ->assertJsonPath('result.email', 'taro.event@example.com')
            ->assertJsonPath('result.company_name', null);
    }

    public function test_it_returns_not_found_when_the_scanned_user_does_not_exist(): void
    {
        $this->mock(UserService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('11111111-1111-4111-8111-111111111111', 0)
                ->andThrow(new \Exception('User not found', 404));
        });

        $response = $this->getJson('/api/v1/reception/users/11111111-1111-4111-8111-111111111111');

        $response->assertStatus(404)
            ->assertJsonPath('message', '来場者が見つかりません')
            ->assertJsonPath('result.user_uuid', '11111111-1111-4111-8111-111111111111');
    }
}
