<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Nueva Cartera<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Nueva Cartera de Cobro</h3>
                    <a href="<?= site_url('portfolios') ?>" class="btn btn-secondary">Volver</a>
                </div>
            </div>
            <div class="card-body">
                <form action="<?= site_url('portfolios/create') ?>" method="post">
                    <?= csrf_field() ?>
                    
                    <?php if($auth->hasRole('superadmin')): ?>
                        <?php if ($auth->organizationId()): ?>
                            <?php 
                                $orgModel = new \App\Models\OrganizationModel();
                                $org = $orgModel->find($auth->organizationId());
                                $orgName = $org ? $org['name'] : 'Desconocida';
                            ?>
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-building"></i> Creando cartera para: <strong><?= esc($orgName) ?></strong>
                            </div>
                            <input type="hidden" name="organization_id" value="<?= $auth->organizationId() ?>">
                        <?php else: ?>
                            <div class="mb-3">
                                <label for="organization_id" class="form-label">Organización *</label>
                                <select class="form-select" id="organization_id" name="organization_id" required>
                                    <option value="">Seleccione una organización</option>
                                    <?php foreach($organizations as $org): ?>
                                        <option value="<?= $org['id'] ?>"><?= $org['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    <?php elseif (!$auth->hasRole('superadmin')): ?>
                        <!-- For non-superadmins, just add the hidden organization ID -->
                        <input type="hidden" name="organization_id" value="<?= $auth->organizationId() ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= old('name') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= old('description') ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Estado *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?= old('status') == 'active' ? 'selected' : '' ?>>Activa</option>
                            <option value="inactive" <?= old('status') == 'inactive' ? 'selected' : '' ?>>Inactiva</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">Asignar Cobrador</h5>
                            <div class="mb-3">
                                <div class="users-list" style="max-height: 300px; overflow-y: auto;">
                                    <div id="users-container">
                                    <?php if(isset($users)): ?>
                                        <?php foreach ($users as $user): ?>
                                            <?php if ($user['role'] == 'admin' || $user['role'] == 'user'): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input user-radio" 
                                                           type="radio" 
                                                           name="user_id" 
                                                           id="user_<?= $user['id'] ?>" 
                                                           value="<?= $user['id'] ?>" 
                                                           required>
                                                    <label class="form-check-label" for="user_<?= $user['id'] ?>">
                                                        <?= esc($user['name']) ?> 
                                                        <small class="text-muted">(<?= esc($user['email']) ?>)</small>
                                                    </label>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5 class="mb-3">Asignar Clientes</h5>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="select_all_clients">
                                    <label class="form-check-label" for="select_all_clients">
                                        <strong>Seleccionar Todos</strong>
                                    </label>
                                </div>
                                <hr>
                                <div class="clients-list" style="max-height: 300px; overflow-y: auto;">
                                        <div id="clients-container">
                                        <?php if(isset($clients)): ?>
                                            <?php foreach ($clients as $client): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input client-checkbox" type="checkbox" name="client_ids[]" id="client_<?= $client['id'] ?>" value="<?= $client['id'] ?>" <?= (old('client_ids') && in_array($client['id'], old('client_ids'))) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="client_<?= $client['id'] ?>">
                                                        <?= $client['business_name'] ?> (<?= $client['document_number'] ?>)
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted">Seleccione una organización para ver clientes disponibles</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Guardar Cartera</button>
                        <a href="<?= site_url('portfolios') ?>" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var usersContainer = document.getElementById('users-container');
    var clientsContainer = document.getElementById('clients-container');
    
    // Seleccionar todos los clientes
    document.getElementById('select_all_clients').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('.client-checkbox');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = this.checked;
        }, this);
    });

    // Función para cargar datos de la organización
    function loadOrganizationData(organizationId) {
        if (!organizationId) return;

        // Cargar clientes
        fetch(`/portfolios/organization/${organizationId}/clients`)
            .then(response => response.json())
            .then(data => {
                updateClientsContainer(data.clients);
            })
            .catch(error => {
                console.error('Error loading clients:', error);
                clientsContainer.innerHTML = '<p class="text-danger">Error al cargar clientes</p>';
            });
    }

    // Manejar organización predefinida (hidden input)
    var hiddenOrgInput = document.querySelector('input[type="hidden"][name="organization_id"]');
    if (hiddenOrgInput) {
        loadOrganizationData(hiddenOrgInput.value);
    }

    // Manejar cambio de organización (select)
    var organizationSelect = document.getElementById('organization_id');
    if (organizationSelect) {
        organizationSelect.addEventListener('change', function() {
            var organizationId = this.value;
            if (organizationId) {
                loadOrganizationData(organizationId);
            } else {
                usersContainer.innerHTML = '<p class="text-muted">Seleccione una organización para ver usuarios disponibles</p>';
                clientsContainer.innerHTML = '<p class="text-muted">Seleccione una organización para ver clientes disponibles</p>';
            }
        });
    }

    function updateClientsContainer(clients) {
        if (!clients || clients.length === 0) {
            clientsContainer.innerHTML = '<p class="text-muted">No hay clientes disponibles</p>';
            return;
        }

        let html = '';
        clients.forEach(client => {
            html += `
                <div class="form-check">
                    <input class="form-check-input client-checkbox" type="checkbox" name="client_ids[]" id="client_${client.id}" value="${client.id}">
                    <label class="form-check-label" for="client_${client.id}">
                        ${client.business_name}
                    </label>
                </div>
            `;
        });
        clientsContainer.innerHTML = html;
    }
});
</script>
<?= $this->endSection() ?>