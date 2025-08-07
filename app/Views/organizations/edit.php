<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Organización<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Editar Organización</h3>
                    <a href="<?= site_url('organizations') ?>" class="btn btn-secondary">Volver</a>
                </div>
            </div>
            <div class="card-body">
                <form action="<?= site_url('organizations/' . $organization['uuid']) ?>" method="post">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre</label>
                        <input type="text" class="form-control <?= session('errors.name') ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= old('name', $organization['name']) ?>" required>
                        <?php if (session('errors.name')): ?>
                            <div class="invalid-feedback"><?= session('errors.name') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="code" class="form-label">Código</label>
                        <input type="text" class="form-control <?= session('errors.code') ? 'is-invalid' : '' ?>" id="code" name="code" value="<?= old('code', $organization['code']) ?>" readonly>
                        <div class="form-text">El código no se puede modificar una vez creado.</div>
                        <?php if (session('errors.code')): ?>
                            <div class="invalid-feedback"><?= session('errors.code') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea class="form-control <?= session('errors.description') ? 'is-invalid' : '' ?>" id="description" name="description" rows="3"><?= old('description', $organization['description']) ?></textarea>
                        <?php if (session('errors.description')): ?>
                            <div class="invalid-feedback"><?= session('errors.description') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Estado</label>
                        <select class="form-select <?= session('errors.status') ? 'is-invalid' : '' ?>" id="status" name="status" required>
                            <option value="active" <?= old('status', $organization['status']) == 'active' ? 'selected' : '' ?>>Activo</option>
                            <option value="inactive" <?= old('status', $organization['status']) == 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                        <?php if (session('errors.status')): ?>
                            <div class="invalid-feedback"><?= session('errors.status') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Ligo Payment Integration Settings -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-qr-code-scan"></i> Configuración de Pagos Ligo QR
                                </h5>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-<?= ($organization['ligo_environment'] ?? 'dev') === 'prod' ? 'danger' : 'warning' ?> me-2">
                                        <?= ($organization['ligo_environment'] ?? 'dev') === 'prod' ? 'PRODUCCIÓN' : 'DESARROLLO' ?>
                                    </span>
                                    <?php if (!empty($organization['ligo_token']) && !empty($organization['ligo_token_expiry'])): ?>
                                        <?php 
                                        $expiry = strtotime($organization['ligo_token_expiry']);
                                        $isExpired = $expiry <= time();
                                        ?>
                                        <span class="badge bg-<?= $isExpired ? 'danger' : 'success' ?>">
                                            Token <?= $isExpired ? 'Expirado' : 'Válido' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Habilitación General -->
                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" id="ligo_enabled" name="ligo_enabled" value="1" <?= old('ligo_enabled', $organization['ligo_enabled'] ?? 0) == 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ligo_enabled">
                                        <strong>Habilitar pagos con QR de Ligo</strong>
                                    </label>
                                </div>
                            </div>

                            <!-- Environment Toggle -->
                            <div class="mb-4">
                                <label class="form-label"><strong>Entorno Activo</strong></label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="radio" class="form-check-input" id="env_dev" name="ligo_environment" value="dev" <?= old('ligo_environment', $organization['ligo_environment'] ?? 'dev') === 'dev' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="env_dev">
                                                <i class="bi bi-gear-fill text-warning"></i> <strong>Desarrollo</strong>
                                                <br><small class="text-muted">Para pruebas y desarrollo</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="radio" class="form-check-input" id="env_prod" name="ligo_environment" value="prod" <?= old('ligo_environment', $organization['ligo_environment'] ?? 'dev') === 'prod' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="env_prod">
                                                <i class="bi bi-shield-fill-check text-danger"></i> <strong>Producción</strong>
                                                <br><small class="text-muted">Para transacciones reales</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-info-circle"></i> 
                                    El sistema cambiará automáticamente entre las URLs de desarrollo y producción según el entorno seleccionado.
                                </div>
                            </div>

                            <!-- Credenciales Básicas (Compartidas) -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="ligo_username" class="form-label">
                                        <i class="bi bi-person"></i> Username
                                    </label>
                                    <input type="text" class="form-control <?= session('errors.ligo_username') ? 'is-invalid' : '' ?>" id="ligo_username" name="ligo_username" value="<?= old('ligo_username', $organization['ligo_username'] ?? '') ?>">
                                    <?php if (session('errors.ligo_username')): ?>
                                        <div class="invalid-feedback"><?= session('errors.ligo_username') ?></div>
                                    <?php endif; ?>
                                    <div class="form-text">Username asignado por Ligo</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="ligo_password" class="form-label">
                                        <i class="bi bi-key"></i> Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control <?= session('errors.ligo_password') ? 'is-invalid' : '' ?>" id="ligo_password" name="ligo_password" value="<?= old('ligo_password', $organization['ligo_password'] ?? '') ?>">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('ligo_password')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <?php if (session('errors.ligo_password')): ?>
                                        <div class="invalid-feedback"><?= session('errors.ligo_password') ?></div>
                                    <?php endif; ?>
                                    <div class="form-text">Deje en blanco para mantener la contraseña actual</div>
                                </div>
                            </div>

                            <!-- Configuración de Empresa -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="ligo_company_id" class="form-label">
                                        <i class="bi bi-building"></i> Company ID
                                    </label>
                                    <input type="text" class="form-control <?= session('errors.ligo_company_id') ? 'is-invalid' : '' ?>" id="ligo_company_id" name="ligo_company_id" value="<?= old('ligo_company_id', $organization['ligo_company_id'] ?? '') ?>">
                                    <?php if (session('errors.ligo_company_id')): ?>
                                        <div class="invalid-feedback"><?= session('errors.ligo_company_id') ?></div>
                                    <?php endif; ?>
                                    <div class="form-text">UUID de empresa proporcionado por Ligo</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="ligo_account_id" class="form-label">
                                        <i class="bi bi-credit-card"></i> Account ID
                                    </label>
                                    <input type="text" class="form-control <?= session('errors.ligo_account_id') ? 'is-invalid' : '' ?>" id="ligo_account_id" name="ligo_account_id" value="<?= old('ligo_account_id', $organization['ligo_account_id'] ?? '') ?>">
                                    <?php if (session('errors.ligo_account_id')): ?>
                                        <div class="invalid-feedback"><?= session('errors.ligo_account_id') ?></div>
                                    <?php endif; ?>
                                    <div class="form-text">ID de cuenta para QR (opcional)</div>
                                </div>
                            </div>

                            <!-- Merchant Code -->
                            <div class="mb-4">
                                <label for="ligo_merchant_code" class="form-label">
                                    <i class="bi bi-shop"></i> Merchant Code
                                </label>
                                <input type="text" class="form-control <?= session('errors.ligo_merchant_code') ? 'is-invalid' : '' ?>" id="ligo_merchant_code" name="ligo_merchant_code" value="<?= old('ligo_merchant_code', $organization['ligo_merchant_code'] ?? '') ?>" placeholder="4829">
                                <?php if (session('errors.ligo_merchant_code')): ?>
                                    <div class="invalid-feedback"><?= session('errors.ligo_merchant_code') ?></div>
                                <?php endif; ?>
                                <div class="form-text">Código de categoría del comercio según ISO 18245 (por defecto: 4829)</div>
                            </div>
                            
                            <!-- Llave Privada RSA -->
                            <div class="mb-4">
                                <label for="ligo_private_key" class="form-label">
                                    <i class="bi bi-shield-lock"></i> Llave Privada RSA
                                </label>
                                <textarea class="form-control <?= session('errors.ligo_private_key') ? 'is-invalid' : '' ?>" id="ligo_private_key" name="ligo_private_key" rows="6" style="font-family: monospace; font-size: 0.875rem;"><?= old('ligo_private_key', $organization['ligo_private_key'] ?? '') ?></textarea>
                                <?php if (session('errors.ligo_private_key')): ?>
                                    <div class="invalid-feedback"><?= session('errors.ligo_private_key') ?></div>
                                <?php endif; ?>
                                <div class="form-text">
                                    <i class="bi bi-info-circle"></i> 
                                    Llave privada RSA generada para firmar tokens JWT. Debe enviarse la llave pública correspondiente a Ligo.
                                </div>
                            </div>

                            <!-- URLs de API (Información) -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="bi bi-gear text-warning"></i> Desarrollo
                                            </h6>
                                            <p class="card-text small">
                                                <strong>Auth:</strong><br>
                                                <code>cce-auth-dev.ligocloud.tech</code><br>
                                                <strong>API:</strong><br>
                                                <code>cce-api-gateway-dev.ligocloud.tech</code><br>
                                                <strong>SSL:</strong> Deshabilitado
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="bi bi-shield-check text-danger"></i> Producción
                                            </h6>
                                            <p class="card-text small">
                                                <strong>Auth:</strong><br>
                                                <code>cce-auth-prod.ligocloud.tech</code><br>
                                                <strong>API:</strong><br>
                                                <code>cce-api-gateway-prod.ligocloud.tech</code><br>
                                                <strong>SSL:</strong> Habilitado
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// Auto-update badge color when environment changes
document.addEventListener('DOMContentLoaded', function() {
    const envRadios = document.querySelectorAll('input[name="ligo_environment"]');
    const badge = document.querySelector('.badge');
    
    envRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'prod') {
                badge.className = 'badge bg-danger me-2';
                badge.textContent = 'PRODUCCIÓN';
            } else {
                badge.className = 'badge bg-warning me-2';
                badge.textContent = 'DESARROLLO';
            }
        });
    });
    
    // Show/hide fields based on Ligo enabled
    const ligoEnabled = document.getElementById('ligo_enabled');
    const ligoFields = document.querySelector('.card-body').children;
    
    function toggleLigoFields() {
        for (let i = 1; i < ligoFields.length; i++) { // Skip first child (enable checkbox)
            ligoFields[i].style.opacity = ligoEnabled.checked ? '1' : '0.5';
        }
    }
    
    ligoEnabled.addEventListener('change', toggleLigoFields);
    toggleLigoFields(); // Initial state
});
</script>

<?= $this->endSection() ?>