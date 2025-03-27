<?php

namespace App\Controllers\Api;

use App\Models\ClientModel;
use CodeIgniter\RESTful\ResourceController;

class OrganizationController extends ResourceController
{
    protected $format = 'json';

    /**
     * Get all clients for a specific organization
     *
     * @param int $organizationId
     * @return mixed
     */
    public function clients($organizationId = null)
    {
        if (!$organizationId) {
            return $this->failNotFound('Organization ID is required');
        }

        // Verificar acceso a la organización
        $user = session()->get('user');
        if (!$user) {
            return $this->failUnauthorized('User not authenticated');
        }

        // Solo superadmin puede ver clientes de cualquier organización
        // Los demás usuarios solo pueden ver clientes de su organización
        if ($user['role'] !== 'superadmin' && $user['organization_id'] != $organizationId) {
            return $this->failForbidden('You do not have access to this organization\'s clients');
        }

        $clientModel = new ClientModel();
        $clients = $clientModel->where('organization_id', $organizationId)
                             ->where('status', 'active')
                             ->orderBy('business_name', 'ASC')
                             ->findAll();

        if (empty($clients)) {
            return $this->respond([
                'status' => 'error',
                'message' => 'No hay clientes disponibles para la organización seleccionada.',
                'clients' => []
            ]);
        }

        return $this->respond([
            'status' => 'success',
            'message' => 'Clientes encontrados',
            'clients' => $clients
        ]);
    }
}
