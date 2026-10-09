<?php

declare(strict_types=1);

namespace Geminus\Admin\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAttachments extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'auto_increment' => true],
            'resource_type' => ['type' => 'VARCHAR', 'constraint' => 64],
            'resource_id'   => ['type' => 'BIGINT'],
            'filename'      => ['type' => 'VARCHAR', 'constraint' => 64],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'mime_type'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'size_bytes'    => ['type' => 'BIGINT'],
            'uploaded_by'   => ['type' => 'INT'],
            'created_at'    => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['resource_type', 'resource_id', 'id']);
        $this->forge->addUniqueKey('filename');
        $this->forge->createTable('admin_attachments');
    }

    public function down(): void
    {
        $this->forge->dropTable('admin_attachments');
    }
}
