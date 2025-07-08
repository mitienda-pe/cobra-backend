<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLigoQRHashesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'hash' => [
                'type'       => 'VARCHAR',
                'constraint'=> 128,
                'unique'     => true,
            ],
            'order_id' => [
                'type'       => 'VARCHAR',
                'constraint'=> 64,
            ],
            'invoice_id' => [
                'type'       => 'INT',
                'constraint'=> 11,
                'null'       => true,
            ],
            'instalment_id' => [
                'type'       => 'INT',
                'constraint'=> 11,
                'null'       => true,
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint'=> '12,2',
            ],
            'currency' => [
                'type'       => 'VARCHAR',
                'constraint'=> 8,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint'=> 255,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('ligo_qr_hashes');
    }

    public function down()
    {
        $this->forge->dropTable('ligo_qr_hashes');
    }
}
