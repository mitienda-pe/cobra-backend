<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Nuevo Usuario<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Nuevo Usuario</h3>
                    <a href="<?= site_url('users') ?>" class="btn btn-secondary">Volver</a>
                </div>
            </div>
            <div class="card-body">
                <form action="<?= site_url('users/create') ?>" method="post">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= old('name') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= old('email') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmar Contraseña</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Rol</label>
                        <select class="form-select" id="role" name="role" required>
                            <?php if ($auth->hasRole('superadmin')): ?>
                                <option value="superadmin" <?= old('role') == 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                            <?php endif; ?>
                            <option value="admin" <?= old('role') == 'admin' ? 'selected' : '' ?>>Administrador</option>
                            <option value="user" <?= old('role') == 'user' ? 'selected' : '' ?>>Usuario</option>
                        </select>
                    </div>
                    
                    <?php if ($auth->hasRole('superadmin')): ?>
                        <div class="mb-3" id="organization-container">
                            <label for="organization_id" class="form-label">Organización</label>
                            <select class="form-select" id="organization_id" name="organization_id">
                                <option value="">Seleccionar Organización</option>
                                <?php foreach ($organizations as $org): ?>
                                    <option value="<?= $org['id'] ?>" <?= old('organization_id') == $org['id'] ? 'selected' : '' ?>>
                                        <?= $org['name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Sólo para roles Administrador y Usuario. Superadmin no requiere organización.</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($auth->hasRole('superadmin')): ?>
<script>
// Script para mostrar/ocultar el campo de organización según el rol seleccionado
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const organizationContainer = document.getElementById('organization-container');
    
    function toggleOrganizationField() {
        if (roleSelect.value === 'superadmin') {
            organizationContainer.style.display = 'none';
        } else {
            organizationContainer.style.display = 'block';
        }
    }
    
    // Inicializar
    toggleOrganizationField();
    
    // Agregar evento de cambio
    roleSelect.addEventListener('change', toggleOrganizationField);
});
</script>
<?php endif; ?>
<?= $this->endSection() ?>