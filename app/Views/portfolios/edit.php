<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Cartera<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Editar Cartera de Cobro</h3>
                    <a href="<?= site_url('portfolios') ?>" class="btn btn-secondary">Volver</a>
                </div>
            </div>
            <div class="card-body">
                <form action="<?= site_url('portfolios/edit/' . $portfolio['id']) ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= old('name', $portfolio['name']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= old('description', $portfolio['description']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Estado *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?= old('status', $portfolio['status']) == 'active' ? 'selected' : '' ?>>Activa</option>
                            <option value="inactive" <?= old('status', $portfolio['status']) == 'inactive' ? 'selected' : '' ?>>Inactiva</option>
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
                                    <?php if(empty($users)): ?>
                                        <p class="text-muted">No hay usuarios disponibles para esta organización</p>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <?php if ($user['role'] == 'admin' || $user['role'] == 'user'): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input user-checkbox" type="checkbox" 
                                                        name="user_ids[]" id="user_<?= $user['id'] ?>" 
                                                        value="<?= $user['id'] ?>" 
                                                        <?= (in_array($user['id'], $assignedUserIds)) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="user_<?= $user['id'] ?>">
                                                        <?= $user['name'] ?> (<?= ucfirst($user['role']) ?>)
                                                    </label>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
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
                                    <?php if(empty($clients)): ?>
                                        <p class="text-muted">No hay clientes disponibles para esta organización</p>
                                    <?php else: ?>
                                        <?php foreach ($clients as $client): ?>
                                            <div class="form-check">
                                                <input class="form-check-input client-checkbox" type="checkbox" 
                                                    name="client_ids[]" id="client_<?= $client['id'] ?>" 
                                                    value="<?= $client['id'] ?>" 
                                                    <?= (in_array($client['id'], $assignedClientIds)) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="client_<?= $client['id'] ?>">
                                                    <?= $client['business_name'] ?> (<?= $client['document_number'] ?>)
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">Actualizar Cartera</button>
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
    
    // Verificar si todos los checkboxes están seleccionados al cargar
    function updateSelectAllCheckboxes() {
        var userCheckboxes = document.querySelectorAll('.user-checkbox');
        var clientCheckboxes = document.querySelectorAll('.client-checkbox');
        
        if (userCheckboxes.length > 0) {
            var allUsersChecked = Array.from(userCheckboxes).every(function(checkbox) {
                return checkbox.checked;
            });
            document.getElementById('select_all_users').checked = allUsersChecked;
        } else {
            document.getElementById('select_all_users').disabled = true;
        }
        
        if (clientCheckboxes.length > 0) {
            var allClientsChecked = Array.from(clientCheckboxes).every(function(checkbox) {
                return checkbox.checked;
            });
            document.getElementById('select_all_clients').checked = allClientsChecked;
        } else {
            document.getElementById('select_all_clients').disabled = true;
        }
    }
    
    updateSelectAllCheckboxes();
});
</script>
<?= $this->endSection() ?>