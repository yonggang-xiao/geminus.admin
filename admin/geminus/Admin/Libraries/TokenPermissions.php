<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\AccessToken;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;

class TokenPermissions
{
    public function available(User $user, array $catalog): array
    {
        return array_filter($catalog, static fn ($description, $permission): bool => is_string($permission) && ! str_contains($permission, '*') && $user->can($permission), ARRAY_FILTER_USE_BOTH);
    }

    public function select(User $user, array $catalog, mixed $submitted): array
    {
        if (! is_array($submitted) || $submitted === []) {
            throw new InvalidArgumentException('Select at least one permission.');
        }

        $available = $this->available($user, $catalog);

        foreach ($submitted as $permission) {
            if (! is_string($permission) || ! array_key_exists($permission, $available)) {
                throw new InvalidArgumentException('Invalid token permission.');
            }
        }

        return array_values(array_unique($submitted));
    }

    public function allows(User $user, array $catalog, array $scope, string $permission): bool
    {
        foreach ($scope as $item) {
            if (! is_string($item) || str_contains($item, '*')) {
                return false;
            }
        }

        return array_key_exists($permission, $catalog) && in_array($permission, $scope, true) && $user->can($permission);
    }

    public function describe(User $user, array $catalog, AccessToken $token, int $unusedLifetime): array
    {
        $scope   = $token->scopes;
        $invalid = $user->isBanned() || $scope === []
                                     || ($token->expires !== null && $token->expires->isBefore(Time::now('UTC')))
                                     || ($token->last_used_at !== null && $token->last_used_at->isBefore(Time::now('UTC')->subSeconds($unusedLifetime)));
        $legacy = false;

        foreach ($scope as $permission) {
            if (! is_string($permission) || str_contains($permission, '*')) {
                $legacy = true;
            }
        }
        $rows = [];

        foreach ($scope as $permission) {
            $permission = is_string($permission) ? $permission : '';
            $state      = match (true) {
                $legacy                                   => 'tokenLegacy',
                $invalid                                  => 'tokenInvalid',
                ! array_key_exists($permission, $catalog) => 'tokenPermissionUnregistered',
                ! $user->can($permission)                 => 'tokenPermissionDenied',
                default                                   => 'tokenPermissionActive',
            };
            $rows[] = ['permission' => $permission, 'description' => $catalog[$permission] ?? '', 'state' => $state];
        }

        return ['state' => $legacy ? 'tokenLegacy' : ($invalid ? 'tokenInvalid' : null), 'permissions' => $rows];
    }
}
