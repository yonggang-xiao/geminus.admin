<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use CodeIgniter\Shield\Entities\User;

final class UserManagementPolicy
{
    public function __construct(private readonly User $actor)
    {
    }

    public function assignableRoles(): array
    {
        $roles = setting('AuthGroups.groups');
        unset($roles['superadmin']);

        if (! $this->actor->can('users.manage-admins')) {
            unset($roles['admin']);
        }

        if (! $this->actor->inGroup('superadmin')) {
            $matrix = setting('AuthGroups.matrix');

            foreach ($roles as $name => $details) {
                foreach ($matrix[$name] ?? [] as $grant) {
                    if (str_contains($grant, '*') || ! $this->actor->can($grant)) {
                        unset($roles[$name]);

                        break;
                    }
                }
            }
        }

        return $roles;
    }

    public function canManage(User $target): bool
    {
        $groups = $target->getGroups() ?? [];
        if (count($groups) > 1 || array_diff($groups, array_keys($this->assignableRoles())) !== []) {
            return false;
        }

        if (! $this->actor->inGroup('superadmin')) {
            foreach ($target->getPermissions() ?? [] as $grant) {
                if (str_contains($grant, '*') || ! $this->actor->can($grant)) {
                    return false;
                }
            }
        }

        return true;
    }
}
