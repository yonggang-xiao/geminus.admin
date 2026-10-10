<?php

declare(strict_types=1);

namespace Geminus\Admin\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotifications extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'BIGINT', 'auto_increment' => true],
            'user_id'           => ['type' => 'INT'],
            'source'            => ['type' => 'VARCHAR', 'constraint' => 64],
            'reference'         => ['type' => 'BIGINT'],
            'title'             => ['type' => 'VARCHAR', 'constraint' => 200],
            'target_route'      => ['type' => 'VARCHAR', 'constraint' => 150],
            'target_parameters' => ['type' => 'TEXT'],
            'permissions'       => ['type' => 'TEXT'],
            'created_at'        => ['type' => 'DATETIME'],
            'read_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'source', 'reference']);
        $this->forge->addKey(['user_id', 'read_at', 'id']);
        $this->forge->createTable('admin_notifications');
    }

    public function down(): void
    {
        $this->forge->dropTable('admin_notifications');
    }
}
