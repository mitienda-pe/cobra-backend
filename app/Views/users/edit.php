<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Usuario<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Editar Usuario</h3>
                    <a href="<?= site_url('users') ?>" class="btn btn-secondary">Volver</a>
                </div>
            </div>
            <div class="card-body">
                <form action="<?= site_url('users/' . $user['id']) ?>" method="post">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= old('name', $user['name']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= old('email', $user['email']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Teléfono Móvil <small class="text-muted">(Requerido para OTP)</small></label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?= old('phone', $user['phone'] ?? '') ?>" required>
                        <div class="form-text">Formato: +123456789 (incluir código de país). Debe ser único para cada usuario.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva Contraseña <small class="text-muted">(Dejar en blanco para mantener la actual)</small></label>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmar Contraseña</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm">
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Rol</label>
                        <select class="form-select" id="role" name="role" required>
                            <?php if ($auth->hasRole('superadmin')): ?>
                                <option value="superadmin" <?= old('role', $user['role']) == 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                            <?php endif; ?>
                            <option value="admin" <?= old('role', $user['role']) == 'admin' ? 'selected' : '' ?>>Administrador</option>
                            <option value="user" <?= old('role', $user['role']) == 'user' ? 'selected' : '' ?>>Usuario</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Estado</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?= old('status', $user['status']) == 'active' ? 'selected' : '' ?>>Activo</option>
                            <option value="inactive" <?= old('status', $user['status']) == 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    </div>
                    
                    <?php if ($auth->hasRole('superadmin')): ?>
                        <div class="mb-3" id="organization-container">
                            <label for="organization_id" class="form-label">Organización</label>
                            <select class="form-select" id="organization_id" name="organization_id">
                                <option value="">Seleccionar Organización</option>
                                <?php foreach ($organizations as $org): ?>
                                    <option value="<?= $org['id'] ?>" <?= old('organization_id', $user['organization_id']) == $org['id'] ? 'selected' : '' ?>>
                                        <?= $org['name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Sólo para roles Administrador y Usuario. Superadmin no requiere organización.</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Actualizar</button>
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