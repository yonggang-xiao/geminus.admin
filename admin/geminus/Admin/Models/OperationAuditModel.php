<?php

declare(strict_types=1);

namespace Geminus\Admin\Models;

use CodeIgniter\Model;

class OperationAuditModel extends Model
{
    protected $table      = 'operation_audit_logs';
    protected $returnType = 'array';

    public function dashboardRecent(): array
    {
        return $this->select('action, target_type, target_id, created_at')->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll(5);
    }
}
