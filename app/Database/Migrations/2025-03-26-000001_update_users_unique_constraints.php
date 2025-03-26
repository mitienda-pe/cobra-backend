<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateUsersUniqueConstraints extends Migration
{
    public function up()
    {
        // Eliminar la restricción única existente del email
        $this->db->query('DROP INDEX IF EXISTS users_email_unique');
        $this->db->query('DROP INDEX IF EXISTS users_phone_unique');
        
        // Crear nuevos índices únicos que consideren deleted_at
        if ($this->db->DBDriver == 'SQLite3') {
            // En SQLite, necesitamos recrear la tabla para agregar una restricción única compuesta
            $this->db->query('CREATE UNIQUE INDEX users_email_deleted_at_unique ON users(email) WHERE deleted_at IS NULL');
            $this->db->query('CREATE UNIQUE INDEX users_phone_deleted_at_unique ON users(phone) WHERE deleted_at IS NULL');
        } else {
            // Para otros motores de base de datos como MySQL
            $this->forge->addKey(['email', 'deleted_at'], true);
            $this->forge->addKey(['phone', 'deleted_at'], true);
        }
    }

    public function down()
    {
        // Eliminar los nuevos índices
        if ($this->db->DBDriver == 'SQLite3') {
            $this->db->query('DROP INDEX IF EXISTS users_email_deleted_at_unique');
            $this->db->query('DROP INDEX IF EXISTS users_phone_deleted_at_unique');
        } else {
            $this->forge->dropKey('users', 'email_deleted_at');
            $this->forge->dropKey('users', 'phone_deleted_at');
        }
        
        // Restaurar los índices originales
        $fields = [
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'unique' => true
            ]
        ];
        $this->forge->modifyColumn('users', $fields);
    }
}
