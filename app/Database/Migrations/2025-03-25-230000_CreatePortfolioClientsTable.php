<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePortfolioClientsTable extends Migration
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
            'portfolio_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'client_id' => [
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
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('portfolio_id', 'portfolios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('client_id', 'clients', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addUniqueKey(['portfolio_id', 'client_id'], 'unique_portfolio_client');
        
        try {
            // El segundo parámetro true significa "IF NOT EXISTS"
            $this->forge->createTable('portfolio_clients', true);
        } catch (\Exception $e) {
            // Si hay un error diferente al de la tabla existente, lo relanzamos
            if (strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }
    }

    public function down()
    {
        try {
            $this->forge->dropTable('portfolio_clients');
        } catch (\Exception $e) {
            // Si hay un error diferente al de la tabla no existente, lo relanzamos
            if (strpos($e->getMessage(), 'no such table') === false) {
                throw $e;
            }
        }
    }
}
