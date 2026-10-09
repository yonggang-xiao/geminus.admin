<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

final class SuperadminGrants
{
    public static function withPermissions(array $grants, array $permissions): array
    {
        return array_values(array_unique(array_map(static function (string $grant): string {
            if (preg_match('/\A([a-z][a-z0-9-]*)\.(?:[a-z][a-z0-9-]*|\*)\z/D', $grant, $matches)) {
                return $matches[1] . '.*';
            }

            return $grant;
        }, [...$grants, ...$permissions])));
    }
}
