<?php

declare(strict_types=1);

namespace Modules\Announcements\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAnnouncements extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 150],
            'body'       => ['type' => 'TEXT'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('example_announcements');
    }

    public function down(): void
    {
        $this->forge->dropTable('example_announcements');
    }
}
