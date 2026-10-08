<?php

declare(strict_types=1);

namespace Geminus\Admin\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOperationAuditLogs extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'auto_increment' => true],
            'actor_id'    => ['type' => 'INT', 'null' => true],
            'action'      => ['type' => 'VARCHAR', 'constraint' => 16],
            'target_type' => ['type' => 'VARCHAR', 'constraint' => 64],
            'target_id'   => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'path'        => ['type' => 'VARCHAR', 'constraint' => 512],
            'result'      => ['type' => 'VARCHAR', 'constraint' => 16],
            'ip_address'  => ['type' => 'VARCHAR', 'constraint' => 45],
            'created_at'  => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('actor_id');
        $this->forge->addKey('target_id');
        $this->forge->addKey('result');
        $this->forge->addKey('created_at');
        $this->forge->createTable('operation_audit_logs');
    }

    public function down(): void
    {
        $this->forge->dropTable('operation_audit_logs');
    }
}
