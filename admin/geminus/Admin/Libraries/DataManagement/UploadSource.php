<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\DataManagement;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Shield\Entities\User;

interface UploadSource
{
    public function visibleResources(User $viewer): ?BaseBuilder;

    public function describe(array $resourceIds): array;
}
