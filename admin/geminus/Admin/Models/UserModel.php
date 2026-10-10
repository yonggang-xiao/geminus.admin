<?php

declare(strict_types=1);

namespace Geminus\Admin\Models;

use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;
use Geminus\Admin\Entities\AdminUser;

class UserModel extends ShieldUserModel
{
    protected $returnType = AdminUser::class;

    public function dashboardCount(bool $bannedOnly = false): int
    {
        if ($bannedOnly) {
            $this->where('status', 'banned');
        }

        return $this->countAllResults();
    }

    public function dashboardRecent(): array
    {
        return $this->select('id, username, created_at')->asArray()->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll(5);
    }

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
