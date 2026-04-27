<?php

namespace App\Services\Eventos\User;

use Throwable;

class EventosUserLookupService
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    /**
     * @return array{
     *     user_uuid: string,
     *     user_id: int|null,
     *     name: string|null,
     *     email: string|null,
     *     company_name: string|null
     * }
     *
     * @throws Throwable
     */
    public function resolveByUuid(string $userUuid): array
    {
        try {
            $payload = $this->userService->getUsersByUuid($userUuid, 0);
        } catch (Throwable $throwable) {
            if ((int) $throwable->getCode() === 404) {
                return $this->emptyUser($userUuid);
            }

            throw $throwable;
        }

        return [
            'user_uuid' => $this->resolveString($payload, [
                'user_qrcode',
                'external_qrcode',
                'user.user_uuid',
                'user_uuid',
                'user.uuid',
                'uuid',
            ]) ?? $userUuid,
            'user_id' => $this->resolveInt($payload, [
                'user.user_id',
                'user_id',
                'user.id',
                'id',
            ]),
            'name' => $this->resolveString($payload, [
                'user.name',
                'name',
            ]) ?? $this->resolveShareProfileFullName($payload),
            'email' => $this->resolveString($payload, [
                'account',
                'user.mail_address',
                'mail_address',
                'user.email',
                'email',
                'user.snap_auth_account',
                'snap_auth_account',
            ]),
            'company_name' => $this->resolveString($payload, [
                'user.company_name',
                'company_name',
                'user.company',
                'company',
            ]),
        ];
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function resolveString(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (! is_string($value)) {
                continue;
            }

            $trimmedValue = trim($value);

            if ($trimmedValue !== '') {
                return $trimmedValue;
            }
        }

        return null;
    }

    private function resolveShareProfileFullName(array $payload): ?string
    {
        $shareProfiles = data_get($payload, 'profiles.share_profiles');

        if (! is_array($shareProfiles)) {
            return null;
        }

        foreach ($shareProfiles as $shareProfile) {
            if (! is_array($shareProfile)) {
                continue;
            }

            $selectorValues = $shareProfile['selector_value'] ?? null;

            if (! is_array($selectorValues)) {
                continue;
            }

            $nameParts = [];

            foreach (['last_name', 'first_name'] as $selectorKey) {
                $value = $this->findSelectorValue($selectorValues, $selectorKey);

                if ($value !== null) {
                    $nameParts[] = $value;
                }
            }

            if ($nameParts !== []) {
                return implode(' ', $nameParts);
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $selectorValues
     */
    private function findSelectorValue(array $selectorValues, string $key): ?string
    {
        foreach ($selectorValues as $selectorValue) {
            if (! is_array($selectorValue)) {
                continue;
            }

            if (($selectorValue['key'] ?? null) !== $key) {
                continue;
            }

            $value = $selectorValue['value'] ?? null;

            if (is_string($value)) {
                $trimmedValue = trim($value);

                return $trimmedValue !== '' ? $trimmedValue : null;
            }

            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function resolveInt(array $payload, array $paths): ?int
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     user_uuid: string,
     *     user_id: null,
     *     name: null,
     *     email: null,
     *     company_name: null
     * }
     */
    private function emptyUser(string $userUuid): array
    {
        return [
            'user_uuid' => $userUuid,
            'user_id' => null,
            'name' => null,
            'email' => null,
            'company_name' => null,
        ];
    }
}
