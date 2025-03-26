<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdatePortfolioRelations extends Migration
{
    public function up()
    {
        // Modificar la tabla client_portfolio
        $this->forge->dropTable('client_portfolio', true);
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'client_uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 8,
            ],
            'portfolio_uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 8,
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
        $this->forge->addKey(['client_uuid', 'portfolio_uuid']);
        $this->forge->createTable('client_portfolio');

        // Modificar la tabla portfolio_user
        $this->forge->dropTable('portfolio_user', true);
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'user_uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 8,
            ],
            'portfolio_uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 8,
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
        $this->forge->addKey(['user_uuid', 'portfolio_uuid']);
        $this->forge->createTable('portfolio_user');
    }

    public function down()
    {
        $this->forge->dropTable('client_portfolio', true);
        $this->forge->dropTable('portfolio_user', true);
    }
}
