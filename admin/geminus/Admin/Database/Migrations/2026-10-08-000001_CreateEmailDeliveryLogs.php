<?php

declare(strict_types=1);

namespace Geminus\Admin\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmailDeliveryLogs extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'job_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'recipient'      => ['type' => 'TEXT'],
            'subject'        => ['type' => 'TEXT'],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 16],
            'failure_reason' => ['type' => 'TEXT', 'null' => true],
            'attempts'       => ['type' => 'INT', 'default' => 0],
            'created_at'     => ['type' => 'DATETIME'],
            'processed_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('job_id');
        $this->forge->createTable('email_delivery_logs');
    }

    public function down(): void
    {
        $this->forge->dropTable('email_delivery_logs');
    }
}
