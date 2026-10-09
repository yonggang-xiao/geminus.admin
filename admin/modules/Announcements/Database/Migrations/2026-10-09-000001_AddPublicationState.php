<?php

declare(strict_types=1);

namespace Modules\Announcements\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPublicationState extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('example_announcements', [
            'status'       => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'draft'],
            'published_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->db->query("ALTER TABLE example_announcements ADD CONSTRAINT example_announcements_publication_check CHECK ((status = 'draft' AND published_at IS NULL) OR (status = 'published' AND published_at IS NOT NULL))");
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE example_announcements DROP CONSTRAINT example_announcements_publication_check');
        $this->forge->dropColumn('example_announcements', ['status', 'published_at']);
    }
}
