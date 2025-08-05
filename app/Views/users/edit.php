<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Usuario<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0">Editar Usuario</h3>
                        <small class="text-muted">Usuario: <?= esc($user['name']) ?></small>
                    </div>
                    <a href="<?= site_url('users') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (session()->has('error')): ?>
                    <div class="alert alert-danger">
                        <?= session('error') ?>
                    </div>
                <?php endif; ?>

                <?php if (session()->has('errors')): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach (session('errors') as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="<?= site_url('users/' . $user['uuid']) ?>" method="post">
                    <?= csrf_field() ?>
                    
                    <!-- Organization Context (automatically handled) -->
                    <?php if ($auth->organizationId()): ?>
                        <input type="hidden" name="organization_id" value="<?= $auth->organizationId() ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre Completo *</label>
                                <input type="text" class="form-control <?= session('errors.name') ? 'is-invalid' : '' ?>" 
                                       id="name" name="name" value="<?= old('name', $user['name']) ?>" required>
                                <?php if (session('errors.name')): ?>
                                    <div class="invalid-feedback"><?= session('errors.name') ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Correo Electrónico *</label>
                                <input type="email" class="form-control <?= session('errors.email') ? 'is-invalid' : '' ?>" 
                                       id="email" name="email" value="<?= old('email', $user['email']) ?>" required>
                                <?php if (session('errors.email')): ?>
                                    <div class="invalid-feedback"><?= session('errors.email') ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">
                            Teléfono Móvil *
                            <small class="text-muted">(Requerido para autenticación OTP)</small>
                        </label>
                        <input type="tel" class="form-control <?= session('errors.phone') ? 'is-invalid' : '' ?>" 
                               id="phone" name="phone" value="<?= old('phone', $user['phone'] ?? '') ?>" required 
                               placeholder="+51987654321">
                        <div class="form-text">Incluir código de país. Ejemplo: +51987654321</div>
                        <?php if (session('errors.phone')): ?>
                            <div class="invalid-feedback"><?= session('errors.phone') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    Nueva Contraseña
                                    <small class="text-muted">(Dejar en blanco para mantener actual)</small>
                                </label>
                                <input type="password" class="form-control <?= session('errors.password') ? 'is-invalid' : '' ?>" 
                                       id="password" name="password" minlength="6">
                                <div class="form-text">Mínimo 6 caracteres</div>
                                <?php if (session('errors.password')): ?>
                                    <div class="invalid-feedback"><?= session('errors.password') ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Confirmar Contraseña</label>
                                <input type="password" class="form-control <?= session('errors.password_confirm') ? 'is-invalid' : '' ?>" 
                                       id="password_confirm" name="password_confirm" minlength="6">
                                <?php if (session('errors.password_confirm')): ?>
                                    <div class="invalid-feedback"><?= session('errors.password_confirm') ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Rol *</label>
                                <select class="form-select <?= session('errors.role') ? 'is-invalid' : '' ?>" 
                                        id="role" name="role" required>
                                    <?php if ($auth->hasRole('superadmin')): ?>
                                        <option value="superadmin" <?= old('role', $user['role']) == 'superadmin' ? 'selected' : '' ?>>
                                            Superadministrador
                                        </option>
                                    <?php endif; ?>
                                    <option value="admin" <?= old('role', $user['role']) == 'admin' ? 'selected' : '' ?>>
                                        Administrador
                                    </option>
                                    <option value="user" <?= old('role', $user['role']) == 'user' ? 'selected' : '' ?>>
                                        Cobrador
                                    </option>
                                </select>
                                <?php if (session('errors.role')): ?>
                                    <div class="invalid-feedback"><?= session('errors.role') ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Estado</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" 
                                           id="status" name="status" value="active" 
                                           <?= old('status', $user['status']) == 'active' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="status">
                                        <span id="status-text"><?= old('status', $user['status']) == 'active' ? 'Activo' : 'Inactivo' ?></span>
                                    </label>
                                </div>
                                <div class="form-text">Los usuarios inactivos no pueden acceder al sistema</div>
                            </div>
                        </div>
                    </div>

                    <div class="role-description mb-4" id="role-description" style="display: none;">
                        <div class="alert alert-info">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-info-circle me-2 mt-1"></i>
                                <div>
                                    <strong id="role-title"></strong>
                                    <p class="mb-0" id="role-text"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Info Section -->
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <h6 class="card-title">Información del Usuario</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">UUID:</small>
                                    <div class="font-monospace small"><?= $user['uuid'] ?></div>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">Fecha de registro:</small>
                                    <div><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></div>
                                </div>
                            </div>
                            <?php if ($user['updated_at']): ?>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <small class="text-muted">Última actualización:</small>
                                        <div><?= date('d/m/Y H:i', strtotime($user['updated_at'])) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-check"></i> Actualizar Usuario
                        </button>
                        <a href="<?= site_url('users') ?>" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const statusToggle = document.getElementById('status');
    const statusText = document.getElementById('status-text');
    const roleDescription = document.getElementById('role-description');
    const roleTitle = document.getElementById('role-title');
    const roleTextEl = document.getElementById('role-text');

    // Role descriptions
    const roleDescriptions = {
        'superadmin': {
            title: 'Superadministrador',
            text: 'Acceso completo al sistema. Puede gestionar múltiples organizaciones, crear otros superadministradores y acceder a todas las funciones.'
        },
        'admin': {
            title: 'Administrador',
            text: 'Administra una organización específica. Puede crear usuarios cobradores, gestionar clientes, facturas y ver reportes de su organización.'
        },
        'user': {
            title: 'Cobrador',
            text: 'Acceso únicamente a la aplicación móvil. Puede ver sus carteras asignadas, registrar pagos y gestionar cobranzas de campo.'
        }
    };

    // Status toggle functionality
    statusToggle.addEventListener('change', function() {
        statusText.textContent = this.checked ? 'Activo' : 'Inactivo';
    });

    // Role description functionality
    function updateRoleDescription() {
        const selectedRole = roleSelect.value;
        
        if (selectedRole && roleDescriptions[selectedRole]) {
            roleTitle.textContent = roleDescriptions[selectedRole].title;
            roleTextEl.textContent = roleDescriptions[selectedRole].text;
            roleDescription.style.display = 'block';
        } else {
            roleDescription.style.display = 'none';
        }
    }

    // Initialize role description
    updateRoleDescription();
    
    roleSelect.addEventListener('change', updateRoleDescription);

    // Password confirmation validation
    const password = document.getElementById('password');
    const passwordConfirm = document.getElementById('password_confirm');

    function validatePasswords() {
        if (password.value && password.value !== passwordConfirm.value) {
            passwordConfirm.setCustomValidity('Las contraseñas no coinciden');
        } else {
            passwordConfirm.setCustomValidity('');
        }
    }

    password.addEventListener('input', validatePasswords);
    passwordConfirm.addEventListener('input', validatePasswords);
});
</script>

<style>
.form-check-input:checked {
    background-color: #198754;
    border-color: #198754;
}

.role-description {
    transition: all 0.3s ease;
}

.alert-info {
    background-color: rgba(13, 202, 240, 0.1);
    border-color: rgba(13, 202, 240, 0.2);
    color: #055160;
}

.font-monospace {
    font-family: 'Courier New', Courier, monospace;
}
</style>

<?= $this->endSection() ?>