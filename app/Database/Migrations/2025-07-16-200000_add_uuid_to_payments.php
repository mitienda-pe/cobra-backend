<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUuidToPayments extends Migration
{
    public function up()
    {
        // Add uuid column to payments table
        $this->forge->addColumn('payments', [
            'uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
                'after' => 'id'
            ]
        ]);
        
        // Generate UUIDs for existing records
        $payments = $this->db->table('payments')->get()->getResult();
        foreach ($payments as $payment) {
            $uuid = $this->generateUuid();
            $this->db->table('payments')
                    ->where('id', $payment->id)
                    ->update(['uuid' => $uuid]);
        }
        
        // Add unique index for uuid
        $this->forge->addKey('uuid', false, true);
        $this->forge->processIndexes('payments');
    }

    public function down()
    {
        // Remove uuid column
        $this->forge->dropColumn('payments', 'uuid');
    }
    
    private function generateUuid()
    {
        // Generate RFC 4122 compliant UUID v4
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}