<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserOtpCodesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'code' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
            ],
            'device_info' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addKey('code');
        $this->forge->addKey('expires_at');
        
        // Enable foreign key constraints for SQLite
        if ($this->db->DBDriver == 'SQLite3') {
            $this->db->query('PRAGMA foreign_keys = ON');
        }
        
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_otp_codes');
    }

    public function down()
    {
        $this->forge->dropTable('user_otp_codes');
    }
}