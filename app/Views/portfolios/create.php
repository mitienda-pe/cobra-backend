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
    
    // Para superadmin: cargar usuarios y clientes al seleccionar organización
    const organizationSelect = document.getElementById('organization_id');
    const hiddenOrgInput = document.querySelector('input[type="hidden"][name="organization_id"]');
    
    // Load clients and users once at page load if we have a hidden organization ID (using global selector)
    if (hiddenOrgInput) {
        const organizationId = hiddenOrgInput.value;
        if (organizationId) {
            // Mostrar mensaje de carga
            document.getElementById('users-container').innerHTML = '<p class="text-muted">Cargando usuarios...</p>';
            document.getElementById('clients-container').innerHTML = '<p class="text-muted">Cargando clientes...</p>';
            
            // Fetch users for selected organization
            fetch('<?= site_url('debug/get-users-by-organization') ?>/' + organizationId)
                .then(response => response.json())
                .then(data => {
                    console.log('Users response:', data);
                    updateUsersContainer(data);
                })
                .catch(error => {
                    console.error('Error fetching users:', error);
                    document.getElementById('users-container').innerHTML = '<p class="text-danger">Error al cargar usuarios</p>';
                });
            
            // Fetch clients for selected organization
            fetch('<?= site_url('debug/get-clients-by-organization') ?>/' + organizationId)
                .then(response => response.json())
                .then(data => {
                    console.log('Clients response:', data);
                    updateClientsContainer(data);
                })
                .catch(error => {
                    console.error('Error fetching clients:', error);
                    document.getElementById('clients-container').innerHTML = '<p class="text-danger">Error al cargar clientes</p>';
                });
        }
    }
    // If we're using the dropdown organization selector 
    else if (organizationSelect) {
        // Limpiar los contenedores al inicio
        document.getElementById('users-container').innerHTML = '<p class="text-muted">Seleccione una organización para ver usuarios disponibles</p>';
        document.getElementById('clients-container').innerHTML = '<p class="text-muted">Seleccione una organización para ver clientes disponibles</p>';
        
        organizationSelect.addEventListener('change', function() {
            const organizationId = this.value;
            if (organizationId) {
                // Mostrar mensaje de carga
                document.getElementById('users-container').innerHTML = '<p class="text-muted">Cargando usuarios...</p>';
                document.getElementById('clients-container').innerHTML = '<p class="text-muted">Cargando clientes...</p>';
                
                // Fetch users for selected organization
                fetch('<?= site_url('debug/get-users-by-organization') ?>/' + organizationId)
                    .then(response => response.json())
                    .then(data => {
                        console.log('Users response:', data);
                        updateUsersContainer(data);
                    })
                    .catch(error => {
                        console.error('Error fetching users:', error);
                        document.getElementById('users-container').innerHTML = '<p class="text-danger">Error al cargar usuarios</p>';
                    });
                
                // Fetch clients for selected organization
                fetch('<?= site_url('debug/get-clients-by-organization') ?>/' + organizationId)
                    .then(response => response.json())
                    .then(data => {
                        console.log('Clients response:', data);
                        updateClientsContainer(data);
                    })
                    .catch(error => {
                        console.error('Error fetching clients:', error);
                        document.getElementById('clients-container').innerHTML = '<p class="text-danger">Error al cargar clientes</p>';
                    });
            } else {
                // Clear containers if no organization selected
                document.getElementById('users-container').innerHTML = '<p class="text-muted">Seleccione una organización para ver usuarios disponibles</p>';
                document.getElementById('clients-container').innerHTML = '<p class="text-muted">Seleccione una organización para ver clientes disponibles</p>';
            }
        });
    }
    
    function updateUsersContainer(data) {
        const container = document.getElementById('users-container');
        // API puede devolver users o directamente un array
        const users = Array.isArray(data) ? data : (data.users || []);
        
        if (users.length === 0) {
            container.innerHTML = '<p class="text-muted">No hay usuarios disponibles para esta organización</p>';
            return;
        }
        
        let html = '';
        users.forEach(user => {
            if (user.role === 'admin' || user.role === 'user') {
                html += `
                    <div class="form-check">
                        <input class="form-check-input user-checkbox" type="checkbox" name="user_ids[]" id="user_${user.id}" value="${user.id}">
                        <label class="form-check-label" for="user_${user.id}">
                            ${user.name} (${user.role.charAt(0).toUpperCase() + user.role.slice(1)})
                        </label>
                    </div>
                `;
            }
        });
        
        if (html === '') {
            container.innerHTML = '<p class="text-muted">No hay usuarios con roles adecuados para esta organización</p>';
        } else {
            container.innerHTML = html;
        }
        
        // Reactivar el checkbox "seleccionar todos"
        document.getElementById('select_all_users').checked = false;
    }
    
    function updateClientsContainer(data) {
        const container = document.getElementById('clients-container');
        // API puede devolver clients o directamente un array
        const clients = Array.isArray(data) ? data : (data.clients || []);
        
        if (clients.length === 0) {
            container.innerHTML = '<p class="text-muted">No hay clientes disponibles para esta organización</p>';
            return;
        }
        
        let html = '';
        clients.forEach(client => {
            html += `
                <div class="form-check">
                    <input class="form-check-input client-checkbox" type="checkbox" name="client_ids[]" id="client_${client.id}" value="${client.id}">
                    <label class="form-check-label" for="client_${client.id}">
                        ${client.business_name} (${client.document_number || 'Sin documento'})
                    </label>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
        // Reactivar el checkbox "seleccionar todos"
        document.getElementById('select_all_clients').checked = false;
    }
});
</script>
<?= $this->endSection() ?>