<?php

declare(strict_types=1);

namespace Geminus\Admin\Config;

use CodeIgniter\Config\BaseConfig;
use Geminus\Admin\Libraries\AuditSubmission;
use InvalidArgumentException;

class OperationAudit extends BaseConfig
{
    public array $operations = [];

    public function __construct()
    {
        parent::__construct();
        new AuditSubmission($this->operations);
    }

    protected function registerProperties()
    {
        $operations = $this->operations;
        parent::registerProperties();

        foreach (static::$registrars as $registrar) {
            if (! method_exists($registrar, 'OperationAudit')) {
                continue;
            }
            $registration = $registrar::OperationAudit();
            if (array_keys($registration) !== ['operations']
                || ! is_array($registration['operations']) || ! array_is_list($registration['operations'])) {
                throw new InvalidArgumentException('Audit registrar must declare an operations list.');
            }
            $operations = array_merge($operations, $registration['operations']);
        }
        $this->operations = $operations;
    }
}
