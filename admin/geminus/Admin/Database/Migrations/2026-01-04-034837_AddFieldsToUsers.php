<?php

namespace Geminus\Admin\Database\Migrations;

use CodeIgniter\Database\Forge;
use CodeIgniter\Database\Migration;

class AddFieldsToUsers extends Migration
{
    private array $tables;

    public function __construct(?Forge $forge = null)
    {
        parent::__construct($forge);

        /** @var \Config\Auth $authConfig */
        $authConfig   = config('Auth');
        $this->tables = $authConfig->tables;
    }

    public function up()
    {
        $fields = [
            'avatar' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
                'comment'    => '头像文件路径或外链 URL'
            ],
            'language' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'default'    => 'en',
                'comment'    => '界面语言首选项'
            ],
            'timezone' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
                'default'    => 'UTC',
                'comment'    => '时区标识，如 Asia/Shanghai'
            ], 
        ];
        $this->forge->addColumn($this->tables['users'], $fields);
    }

    public function down()
    {
        $fields = [
            'avatar',
            'language',
            'timezone',
        ];
        $this->forge->dropColumn($this->tables['users'], $fields);
    }
}
