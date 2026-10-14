<?php

declare(strict_types=1);

namespace Geminus\Admin\Config;

use CodeIgniter\Config\BaseConfig;
use Geminus\Admin\Libraries\AuditSubmission;

class OperationAudit extends BaseConfig
{
    public array $operations = [];

    public function __construct()
    {
        parent::__construct();
        new AuditSubmission($this->operations);
    }
}
