<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIdQrToLigoQrHashes extends Migration
{
    public function up()
    {
        // Add id_qr field to store the idQr from Ligo API response
        $this->forge->addColumn('ligo_qr_hashes', [
            'id_qr' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'after' => 'order_id',
                'comment' => 'idQr from Ligo API response used in webhooks'
            ]
        ]);
        
        // Add index for faster webhook lookups
        $this->forge->addKey('id_qr');
        $this->forge->processIndexes('ligo_qr_hashes');
    }

    public function down()
    {
        $this->forge->dropColumn('ligo_qr_hashes', 'id_qr');
    }
}