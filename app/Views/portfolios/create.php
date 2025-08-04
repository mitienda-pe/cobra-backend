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
                    
                    <!-- Organization Context (automatically handled) -->
                    <?php if ($auth->organizationId()): ?>
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
                                                <div class="form-check">
                                                    <input class="form-check-input user-radio" 
                                                           type="radio" 
                                                           name="user_id" 
                                                           id="user_<?= $user['uuid'] ?>" 
                                                           value="<?= $user['uuid'] ?>" 
                                                           required>
                                                    <label class="form-check-label" for="user_<?= $user['uuid'] ?>">
                                                        <?= esc($user['name']) ?> 
                                                        <small class="text-muted">(<?= esc($user['email']) ?>)</small>
                                                    </label>
                                                </div>
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
                                                    <input class="form-check-input client-checkbox" 
                                                           type="checkbox" 
                                                           name="client_ids[]" 
                                                           id="client_<?= $client['uuid'] ?>" 
                                                           value="<?= $client['uuid'] ?>">
                                                    <label class="form-check-label" for="client_<?= $client['uuid'] ?>">
                                                        <?= esc($client['business_name']) ?>
                                                        <?php if (!empty($client['legal_name']) && $client['legal_name'] !== $client['business_name']): ?>
                                                            <small class="text-muted">(<?= esc($client['legal_name']) ?>)</small>
                                                        <?php endif; ?>
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
    document.getElementById('select_all_clients')?.addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('.client-checkbox');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = this.checked;
        }, this);
    });

    // Manejar cambio de organización
    var orgSelect = document.getElementById('organization_id');
    if (orgSelect) {
        orgSelect.addEventListener('change', function() {
            var option = this.options[this.selectedIndex];
            var organizationUuid = option.getAttribute('data-uuid');
            if (organizationUuid) {
                loadOrganizationData(organizationUuid);
            } else {
                usersContainer.innerHTML = '<p class="text-muted">Seleccione una organización para ver usuarios disponibles</p>';
                clientsContainer.innerHTML = '<p class="text-muted">Seleccione una organización para ver clientes disponibles</p>';
            }
        });
    }

    // Cargar datos de organización predefinida
    var hiddenOrgInput = document.querySelector('input[type="hidden"][name="organization_id"]');
    if (hiddenOrgInput && hiddenOrgInput.value) {
        var orgId = hiddenOrgInput.value;
        // Obtener el UUID de la organización
        fetch(`/api/organizations/${orgId}`)
            .then(response => response.json())
            .then(data => {
                if (data.uuid) {
                    loadOrganizationData(data.uuid);
                }
            })
            .catch(error => {
                console.error('Error loading organization UUID:', error);
            });
    }

    function loadOrganizationData(organizationUuid) {
        // Cargar usuarios disponibles
        fetch(`/portfolios/organization/${organizationUuid}/users`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                usersContainer.innerHTML = renderUsers(data.users);
            })
            .catch(error => {
                console.error('Error loading users:', error);
                usersContainer.innerHTML = '<div class="alert alert-danger">Error al cargar usuarios</div>';
            });

        // Cargar clientes disponibles
        fetch(`/portfolios/organization/${organizationUuid}/clients`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                clientsContainer.innerHTML = renderClients(data.clients);
            })
            .catch(error => {
                console.error('Error loading clients:', error);
                clientsContainer.innerHTML = '<div class="alert alert-danger">Error al cargar clientes</div>';
            });
    }

    function renderUsers(users) {
        if (!users || users.length === 0) {
            return '<p class="text-muted">No hay usuarios disponibles</p>';
        }

        let html = '';
        users.forEach(user => {
            html += `
                <div class="form-check">
                    <input class="form-check-input user-radio" 
                           type="radio" 
                           name="user_id" 
                           id="user_${user.uuid}" 
                           value="${user.uuid}"
                           required>
                    <label class="form-check-label" for="user_${user.uuid}">
                        ${user.name}
                        <small class="text-muted">(${user.email})</small>
                    </label>
                </div>
            `;
        });
        return html;
    }

    function renderClients(clients) {
        if (!clients || clients.length === 0) {
            return '<p class="text-muted">No hay clientes disponibles</p>';
        }

        let html = '';
        clients.forEach(client => {
            html += `
                <div class="form-check">
                    <input class="form-check-input client-checkbox" 
                           type="checkbox" 
                           name="client_ids[]" 
                           id="client_${client.uuid}" 
                           value="${client.uuid}">
                    <label class="form-check-label" for="client_${client.uuid}">
                        ${client.business_name}
                        ${client.legal_name && client.legal_name !== client.business_name ? `<small class="text-muted">(${client.legal_name})</small>` : ''}
                    </label>
                </div>
            `;
        });
        return html;
    }
});
</script>
<?= $this->endSection() ?>