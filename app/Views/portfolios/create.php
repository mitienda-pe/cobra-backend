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
                            <h5 class="mb-3">Asignar Cobradores</h5>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="select_all_users">
                                    <label class="form-check-label" for="select_all_users">
                                        <strong>Seleccionar Todos</strong>
                                    </label>
                                </div>
                                <hr>
                                <div class="users-list" style="max-height: 300px; overflow-y: auto;">
                                        <div id="users-container">
                                        <?php if(isset($users)): ?>
                                            <?php foreach ($users as $user): ?>
                                                <?php if ($user['role'] == 'admin' || $user['role'] == 'user'): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input user-checkbox" type="checkbox" name="user_ids[]" id="user_<?= $user['id'] ?>" value="<?= $user['id'] ?>" <?= (old('user_ids') && in_array($user['id'], old('user_ids'))) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="user_<?= $user['id'] ?>">
                                                            <?= $user['name'] ?> (<?= ucfirst($user['role']) ?>)
                                                        </label>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted">Seleccione una organización para ver usuarios disponibles</p>
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
                    
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">Guardar Cartera</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Seleccionar todos los usuarios
    document.getElementById('select_all_users').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('.user-checkbox');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = this.checked;
        }, this);
    });

    // Seleccionar todos los clientes
    document.getElementById('select_all_clients').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('.client-checkbox');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = this.checked;
        }, this);
    });

    // Manejar el cambio de organización para superadmin
    var organizationSelect = document.getElementById('organization_id');
    var usersContainer = document.getElementById('users-container');
    var clientsContainer = document.getElementById('clients-container');

    if (organizationSelect) {
        // Limpiar los contenedores al inicio
        usersContainer.innerHTML = '<p class="text-muted">Seleccione una organización para ver usuarios disponibles</p>';
        clientsContainer.innerHTML = '<p class="text-muted">Seleccione una organización para ver clientes disponibles</p>';

        organizationSelect.addEventListener('change', function() {
            var organizationId = this.value;
            
            if (organizationId) {
                // Cargar usuarios
                fetch(`/portfolios/organization/${organizationId}/users`)
                    .then(response => response.json())
                    .then(data => {
                        updateUsersContainer(data.users);
                    })
                    .catch(error => {
                        console.error('Error loading users:', error);
                        usersContainer.innerHTML = '<p class="text-danger">Error al cargar usuarios</p>';
                    });

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
            } else {
                usersContainer.innerHTML = '<p class="text-muted">Seleccione una organización para ver usuarios disponibles</p>';
                clientsContainer.innerHTML = '<p class="text-muted">Seleccione una organización para ver clientes disponibles</p>';
            }
        });
    }

    function updateUsersContainer(users) {
        if (!users || users.length === 0) {
            usersContainer.innerHTML = '<p class="text-muted">No hay usuarios disponibles</p>';
            return;
        }

        let html = '';
        users.forEach(user => {
            html += `
                <div class="form-check">
                    <input class="form-check-input user-checkbox" type="checkbox" name="user_ids[]" id="user_${user.id}" value="${user.id}">
                    <label class="form-check-label" for="user_${user.id}">
                        ${user.name} (${user.email})
                    </label>
                </div>
            `;
        });
        usersContainer.innerHTML = html;
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