<?php

declare(strict_types=1);

namespace Geminus\Admin\Models;

use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;
use Geminus\Admin\Entities\AdminUser;

class UserModel extends ShieldUserModel
{
    protected $returnType = AdminUser::class;

    protected function initialize(): void
    {
        parent::initialize();

        $this->allowedFields = [
            ...$this->allowedFields,

            'avatar',
            'language',
            'timezone',
        ];
    }
}
