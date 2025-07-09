<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeletedAtToPortfolioUser extends Migration
{
    public function up()
    {
        // Add deleted_at column to portfolio_user table
        $this->forge->addColumn('portfolio_user', [
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'updated_at'
            ]
        ]);
    }

    public function down()
    {
        // Remove deleted_at column from portfolio_user table
        $this->forge->dropColumn('portfolio_user', 'deleted_at');
    }
}
