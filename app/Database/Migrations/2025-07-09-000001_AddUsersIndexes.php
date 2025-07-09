<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUsersIndexes extends Migration
{
    public function up()
    {
        // Crear índices únicos para email y phone considerando soft deletes
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_users_uuid ON users(uuid)');
        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS users_email_deleted_at_unique ON users(email) WHERE deleted_at IS NULL');
        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS users_phone_deleted_at_unique ON users(phone) WHERE deleted_at IS NULL');
    }

    public function down()
    {
        $this->db->query('DROP INDEX IF EXISTS idx_users_uuid');
        $this->db->query('DROP INDEX IF EXISTS users_email_deleted_at_unique');
        $this->db->query('DROP INDEX IF EXISTS users_phone_deleted_at_unique');
    }
}