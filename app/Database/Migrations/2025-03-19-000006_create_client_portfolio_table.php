<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClientPortfolioTable extends Migration
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
            'client_uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => false,
            ],
            'portfolio_uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addKey(['client_uuid', 'portfolio_uuid'], false, true); // Unique key
        $this->forge->createTable('client_portfolio');
    }

    public function down()
    {
        $this->forge->dropTable('client_portfolio');
    }
}