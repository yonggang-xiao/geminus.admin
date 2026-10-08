<?php

declare(strict_types=1);

namespace Geminus\Admin\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInvitationUserToEmailDeliveryLogs extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('email_delivery_logs', [
            'invited_user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('invited_user_id');
        $this->forge->processIndexes('email_delivery_logs');
    }

    public function down(): void
    {
        $this->forge->dropColumn('email_delivery_logs', 'invited_user_id');
    }
}
