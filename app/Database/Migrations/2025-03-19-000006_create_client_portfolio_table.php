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
            'client_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'portfolio_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
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
        $this->forge->addKey(['client_id', 'portfolio_id'], false, true); // Unique key
        
        // Enable foreign key constraints for SQLite
        if ($this->db->DBDriver == 'SQLite3') {
            $this->db->query('PRAGMA foreign_keys = ON');
        }
        
        $this->forge->addForeignKey('client_id', 'clients', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('portfolio_id', 'portfolios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('client_portfolio');
    }

    public function down()
    {
        $this->forge->dropTable('client_portfolio');
    }
}