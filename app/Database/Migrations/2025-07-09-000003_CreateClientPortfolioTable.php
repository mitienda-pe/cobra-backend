<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClientPortfolioTable extends Migration
{
    public function up()
    {
        // Drop existing table if exists
        $this->forge->dropTable('client_portfolio', true);
        
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'portfolio_uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => false,
            ],
            'client_uuid' => [
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
        $this->forge->addKey(['portfolio_uuid', 'client_uuid'], false, true); // Unique key
        
        $this->forge->createTable('client_portfolio');
    }

    public function down()
    {
        $this->forge->dropTable('client_portfolio');
    }
}