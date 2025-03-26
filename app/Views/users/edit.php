<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Usuario<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Editar Usuario</h3>
                    <a href="<?= site_url('users') ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="<?= site_url('users/' . $user['uuid']) ?>" method="post">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control <?= session('errors.name') ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= old('name', $user['name']) ?>" required>
                        <?php if (session('errors.name')): ?>
                            <div class="invalid-feedback"><?= session('errors.name') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control <?= session('errors.email') ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= old('email', $user['email']) ?>" required>
                        <?php if (session('errors.email')): ?>
                            <div class="invalid-feedback"><?= session('errors.email') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Teléfono Móvil <small class="text-muted">(Requerido para OTP)</small></label>
                        <input type="tel" class="form-control <?= session('errors.phone') ? 'is-invalid' : '' ?>" id="phone" name="phone" value="<?= old('phone', $user['phone'] ?? '') ?>" required>
                        <div class="form-text">Formato: +123456789 (incluir código de país). Debe ser único para cada usuario.</div>
                        <?php if (session('errors.phone')): ?>
                            <div class="invalid-feedback"><?= session('errors.phone') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva Contraseña <small class="text-muted">(Dejar en blanco para mantener la actual)</small></label>
                        <input type="password" class="form-control <?= session('errors.password') ? 'is-invalid' : '' ?>" id="password" name="password">
                        <?php if (session('errors.password')): ?>
                            <div class="invalid-feedback"><?= session('errors.password') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmar Contraseña</label>
                        <input type="password" class="form-control <?= session('errors.password_confirm') ? 'is-invalid' : '' ?>" id="password_confirm" name="password_confirm">
                        <?php if (session('errors.password_confirm')): ?>
                            <div class="invalid-feedback"><?= session('errors.password_confirm') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($auth->hasRole('superadmin')): ?>
                    <div class="mb-3">
                        <label for="role" class="form-label">Rol</label>
                        <select class="form-select <?= session('errors.role') ? 'is-invalid' : '' ?>" id="role" name="role" required>
                            <option value="user" <?= old('role', $user['role']) == 'user' ? 'selected' : '' ?>>Usuario</option>
                            <option value="admin" <?= old('role', $user['role']) == 'admin' ? 'selected' : '' ?>>Administrador</option>
                            <?php if ($auth->user()['role'] == 'superadmin'): ?>
                                <option value="superadmin" <?= old('role', $user['role']) == 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                            <?php endif; ?>
                        </select>
                        <?php if (session('errors.role')): ?>
                            <div class="invalid-feedback"><?= session('errors.role') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3" id="organization_section">
                        <label for="organization_id" class="form-label">Organización</label>
                        <select class="form-select <?= session('errors.organization_id') ? 'is-invalid' : '' ?>" id="organization_id" name="organization_id">
                            <option value="">Seleccione una organización</option>
                            <?php foreach ($organizations as $org): ?>
                                <option value="<?= $org['id'] ?>" <?= old('organization_id', $user['organization_id']) == $org['id'] ? 'selected' : '' ?>>
                                    <?= esc($org['name']) ?> <?= ($org['status'] == 'inactive') ? '(Inactiva)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (session('errors.organization_id')): ?>
                            <div class="invalid-feedback"><?= session('errors.organization_id') ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Estado</label>
                        <select class="form-select <?= session('errors.status') ? 'is-invalid' : '' ?>" id="status" name="status" required>
                            <option value="active" <?= old('status', $user['status']) == 'active' ? 'selected' : '' ?>>Activo</option>
                            <option value="inactive" <?= old('status', $user['status']) == 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                        <?php if (session('errors.status')): ?>
                            <div class="invalid-feedback"><?= session('errors.status') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Actualizar
                        </button>
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
    const organizationContainer = document.getElementById('organization_section');
    
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