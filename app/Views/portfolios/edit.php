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
                <form action="<?= site_url('portfolios/' . $portfolio['uuid'] . '/edit') ?>" method="post">
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
                                                        name="user_ids[]" id="user_<?= $user['uuid'] ?>" 
                                                        value="<?= $user['uuid'] ?>" 
                                                        <?= (in_array($user['uuid'], $assigned_user_ids)) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="user_<?= $user['uuid'] ?>">
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
                                                    name="client_ids[]" id="client_<?= $client['uuid'] ?>" 
                                                    value="<?= $client['uuid'] ?>" 
                                                    <?= (in_array($client['uuid'], $assigned_client_ids)) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="client_<?= $client['uuid'] ?>">
                                                    <?= $client['name'] ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        <a href="<?= site_url('portfolios') ?>" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Seleccionar todos los usuarios
    const selectAllUsers = document.getElementById('select_all_users');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    
    selectAllUsers.addEventListener('change', function() {
        userCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
    
    // Seleccionar todos los clientes
    const selectAllClients = document.getElementById('select_all_clients');
    const clientCheckboxes = document.querySelectorAll('.client-checkbox');
    
    selectAllClients.addEventListener('change', function() {
        clientCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
});
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>