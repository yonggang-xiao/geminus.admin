<?php

declare(strict_types=1);

namespace Geminus\Admin\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use RuntimeException;

class AddCaseInsensitiveLoginIndexes extends Migration
{
    public function up(): void
    {
        $tables     = config('Auth')->tables;
        $users      = $this->db->protectIdentifiers($this->db->prefixTable($tables['users']));
        $identities = $this->db->protectIdentifiers($this->db->prefixTable($tables['identities']));

        if ($this->db->query('SELECT 1 FROM ' . $users . ' WHERE username IS NOT NULL GROUP BY LOWER(username) HAVING COUNT(*) > 1 LIMIT 1')->getFirstRow() !== null) {
            throw new RuntimeException('Resolve case-insensitive username duplicates before migrating.');
        }

        if ($this->db->query('SELECT 1 FROM ' . $identities . ' WHERE type = ? GROUP BY LOWER(secret) HAVING COUNT(*) > 1 LIMIT 1', [Session::ID_TYPE_EMAIL_PASSWORD])->getFirstRow() !== null) {
            throw new RuntimeException('Resolve case-insensitive email duplicates before migrating.');
        }

        $emailIndex = $this->db->protectIdentifiers($this->db->prefixTable('auth_identities_email_lower_unique'));
        $emailType  = $this->db->escape(Session::ID_TYPE_EMAIL_PASSWORD);
        $this->db->query('CREATE EXTENSION IF NOT EXISTS citext');
        $this->db->query('ALTER TABLE ' . $users . ' ALTER COLUMN username TYPE citext USING username::citext');
        $this->db->query('ALTER TABLE ' . $users . ' ADD CONSTRAINT ' . $this->db->protectIdentifiers($this->db->prefixTable('users_username_length')) . ' CHECK (char_length(username) <= 30)');
        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS ' . $emailIndex . ' ON ' . $identities . ' (LOWER(secret)) WHERE type = ' . $emailType);
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX IF EXISTS ' . $this->db->protectIdentifiers($this->db->prefixTable('auth_identities_email_lower_unique')));
        $users = $this->db->protectIdentifiers($this->db->prefixTable(config('Auth')->tables['users']));
        $this->db->query('ALTER TABLE ' . $users . ' DROP CONSTRAINT IF EXISTS ' . $this->db->protectIdentifiers($this->db->prefixTable('users_username_length')));
        $this->db->query('ALTER TABLE ' . $users . ' ALTER COLUMN username TYPE varchar(30) USING username::varchar(30)');
    }
}
