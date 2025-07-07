<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRealHashToLigoQRHashesTable extends Migration
{
    public function up()
    {
        // Agregar nueva columna para el hash real de LIGO
        $this->forge->addColumn('ligo_qr_hashes', [
            'real_hash' => [
                'type'       => 'TEXT',
                'null'       => true,
                'after'      => 'hash'
            ],
            'hash_error' => [
                'type'       => 'TEXT',
                'null'       => true,
                'after'      => 'real_hash'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('ligo_qr_hashes', ['real_hash', 'hash_error']);
    }
}