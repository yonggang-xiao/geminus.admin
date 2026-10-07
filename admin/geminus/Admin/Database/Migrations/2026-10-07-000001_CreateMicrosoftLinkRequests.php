<?php

declare(strict_types=1);

namespace Geminus\Admin\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMicrosoftLinkRequests extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'object_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pending'],
            'expires_at' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['tenant_id', 'object_id']);
        $this->forge->createTable('microsoft_link_requests');
    }

    public function down(): void
    {
        $this->forge->dropTable('microsoft_link_requests');
    }
}
