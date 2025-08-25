<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
<?= $title ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-pencil-square"></i>
                        <?= esc($title) ?>
                    </h3>
                    <div class="card-tools">
                        <a href="<?= site_url('superadmin/ligo-config') ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (session()->getFlashdata('message')): ?>
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            <?= session()->getFlashdata('message') ?>
                        </div>
                    <?php endif; ?>

                    <?php if (session()->getFlashdata('error')): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            <?= session()->getFlashdata('error') ?>
                        </div>
                    <?php endif; ?>

                    <?php if (session()->getFlashdata('errors')): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            <ul class="mb-0">
                                <?php foreach (session()->getFlashdata('errors') as $error): ?>
                                    <li><?= esc($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= site_url('superadmin/ligo-config/update/' . $config['id']) ?>">
                        <?= csrf_field() ?>

                        <div class="row">
                            <!-- Configuration Info -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-info-circle"></i> Información General</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Entorno</label>
                                            <div class="form-control-plaintext">
                                                <span class="badge badge-<?= $config['environment'] === 'prod' ? 'danger' : 'warning' ?> fs-6">
                                                    <?= strtoupper($config['environment']) ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox"
                                                    id="enabled" name="enabled" value="1"
                                                    <?= $config['enabled'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="enabled">
                                                    Configuración habilitada
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox"
                                                    id="is_active" name="is_active" value="1"
                                                    <?= $config['is_active'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="is_active">
                                                    Configuración activa
                                                </label>
                                                <div class="form-text">Solo una configuración por entorno puede estar activa</div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="notes" class="form-label">Notas</label>
                                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                                placeholder="Notas sobre esta configuración..."><?= esc($config['notes'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Authentication Credentials -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-key"></i> Credenciales de Autenticación</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="username" class="form-label">Usuario <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="username" name="username"
                                                value="<?= esc($config['username'] ?? '') ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="password" name="password"
                                                    value="<?= esc($config['password'] ?? '') ?>" required>
                                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                    <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="company_id" class="form-label">Company ID <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="company_id" name="company_id"
                                                value="<?= esc($config['company_id'] ?? '') ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="account_id" class="form-label">Account ID (CCI)</label>
                                            <input type="text" class="form-control" id="account_id" name="account_id"
                                                value="<?= esc($config['account_id'] ?? '') ?>"
                                                placeholder="Cuenta CCI para consultas de balance">
                                        </div>

                                        <div class="mb-3">
                                            <label for="merchant_code" class="form-label">Merchant Code</label>
                                            <input type="text" class="form-control" id="merchant_code" name="merchant_code"
                                                value="<?= esc($config['merchant_code'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <!-- Private Key -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-shield-lock"></i> Clave Privada RSA</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="private_key" class="form-label">Private Key <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="private_key" name="private_key"
                                                rows="8" required style="font-family: monospace; font-size: 12px;"
                                                placeholder="-----BEGIN PRIVATE KEY-----&#10;MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC...&#10;-----END PRIVATE KEY-----"><?= esc($config['private_key'] ?? '') ?></textarea>
                                            <div class="form-text">Clave privada RSA en formato PEM para generar tokens JWT</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <!-- API Configuration -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-globe"></i> URLs de API</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="auth_url" class="form-label">URL de Autenticación</label>
                                            <input type="url" class="form-control" id="auth_url" name="auth_url"
                                                value="<?= esc($config['auth_url'] ?? '') ?>"
                                                placeholder="https://cce-auth-<?= $config['environment'] === 'prod' ? 'prod' : 'dev' ?>.ligocloud.tech">
                                            <div class="form-text">URL base para autenticación. Si está vacío, se usa la por defecto.</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="api_url" class="form-label">URL de API</label>
                                            <input type="url" class="form-control" id="api_url" name="api_url"
                                                value="<?= esc($config['api_url'] ?? '') ?>"
                                                placeholder="https://cce-api-gateway-<?= $config['environment'] === 'prod' ? 'prod' : 'dev' ?>.ligocloud.tech">
                                            <div class="form-text">URL base para operaciones API. Si está vacío, se usa la por defecto.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Debtor Configuration -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-person-badge"></i> Configuración del Deudor</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="debtor_participant_code" class="form-label">Código de Participante <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="debtor_participant_code" name="debtor_participant_code"
                                                value="<?= esc($config['debtor_participant_code'] ?? '0921') ?>" required
                                                placeholder="ej: 0921">
                                            <div class="form-text">Código del participante deudor en el sistema del proveedor</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="debtor_name" class="form-label">Nombre del Deudor</label>
                                            <input type="text" class="form-control" id="debtor_name" name="debtor_name"
                                                value="<?= esc($config['debtor_name'] ?? '') ?>"
                                                placeholder="Nombre completo o razón social">
                                        </div>

                                        <div class="mb-3">
                                            <label for="debtor_id" class="form-label">ID/RUC del Deudor</label>
                                            <input type="text" class="form-control" id="debtor_id" name="debtor_id"
                                                value="<?= esc($config['debtor_id'] ?? '') ?>"
                                                placeholder="20123456789">
                                        </div>

                                        <div class="mb-3">
                                            <label for="debtor_id_code" class="form-label">Código de ID</label>
                                            <select class="form-control" id="debtor_id_code" name="debtor_id_code">
                                                <option value="6" <?= ($config['debtor_id_code'] ?? '') === '6' ? 'selected' : '' ?>>6 - RUC</option>
                                                <option value="1" <?= ($config['debtor_id_code'] ?? '') === '1' ? 'selected' : '' ?>>1 - DNI</option>
                                                <option value="4" <?= ($config['debtor_id_code'] ?? '') === '4' ? 'selected' : '' ?>>4 - Carnet de Extranjería</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="debtor_address_line" class="form-label">Dirección</label>
                                            <input type="text" class="form-control" id="debtor_address_line" name="debtor_address_line"
                                                value="<?= esc($config['debtor_address_line'] ?? '') ?>"
                                                placeholder="Av. Ejemplo 123, Distrito, Ciudad">
                                        </div>

                                        <div class="mb-3">
                                            <label for="debtor_mobile_number" class="form-label">Número Móvil</label>
                                            <input type="text" class="form-control" id="debtor_mobile_number" name="debtor_mobile_number"
                                                value="<?= esc($config['debtor_mobile_number'] ?? '') ?>"
                                                placeholder="999999999">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Webhook Configuration -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-webhook"></i> Configuración de Webhook</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="webhook_secret" class="form-label">Webhook Secret</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="webhook_secret" name="webhook_secret"
                                                    value="<?= esc($config['webhook_secret'] ?? '') ?>"
                                                    placeholder="Secret para validar webhooks">
                                                <button class="btn btn-outline-secondary" type="button" id="toggleWebhookSecret">
                                                    <i class="bi bi-eye" id="toggleWebhookSecretIcon"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="alert alert-info">
                                            <small>
                                                <i class="bi bi-info-circle"></i>
                                                <strong>Configuración Centralizada:</strong><br>
                                                Todas las organizaciones usarán estas credenciales para operaciones de QR.
                                                Los pagos se procesarán usando la cuenta centralizada del superadmin.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <!-- Debtor Configuration -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-person-badge"></i> Configuración del Deudor (Cuenta Superadmin)</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="debtor_name" class="form-label">Nombre del Deudor <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="debtor_name" name="debtor_name"
                                                        value="<?= esc($config['debtor_name'] ?? '') ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="debtor_id" class="form-label">ID/RUC del Deudor <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="debtor_id" name="debtor_id"
                                                        value="<?= esc($config['debtor_id'] ?? '') ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="debtor_id_code" class="form-label">Código de ID <span class="text-danger">*</span></label>
                                                    <select class="form-control" id="debtor_id_code" name="debtor_id_code" required>
                                                        <option value="1" <?= ($config['debtor_id_code'] ?? '') === '1' ? 'selected' : '' ?>>1 - DNI</option>
                                                        <option value="2" <?= ($config['debtor_id_code'] ?? '') === '2' ? 'selected' : '' ?>>2 - CE</option>
                                                        <option value="6" <?= ($config['debtor_id_code'] ?? '') === '6' ? 'selected' : '' ?>>6 - RUC</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="debtor_participant_code" class="form-label">Código de Participante <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="debtor_participant_code" name="debtor_participant_code"
                                                        value="<?= esc($config['debtor_participant_code'] ?? '') ?>" required>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="debtor_address_line" class="form-label">Dirección <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="debtor_address_line" name="debtor_address_line"
                                                        value="<?= esc($config['debtor_address_line'] ?? '') ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="debtor_mobile_number" class="form-label">Número Móvil</label>
                                                    <input type="text" class="form-control" id="debtor_mobile_number" name="debtor_mobile_number"
                                                        value="<?= esc($config['debtor_mobile_number'] ?? '') ?>">
                                                </div>

                                                <div class="mb-3">
                                                    <label for="debtor_phone_number" class="form-label">Número de Teléfono</label>
                                                    <input type="text" class="form-control" id="debtor_phone_number" name="debtor_phone_number"
                                                        value="<?= esc($config['debtor_phone_number'] ?? '') ?>">
                                                </div>

                                                <div class="mb-3">
                                                    <label for="debtor_type_of_person" class="form-label">Tipo de Persona</label>
                                                    <select class="form-control" id="debtor_type_of_person" name="debtor_type_of_person">
                                                        <option value="N" <?= ($config['debtor_type_of_person'] ?? 'N') === 'N' ? 'selected' : '' ?>>N - Natural</option>
                                                        <option value="J" <?= ($config['debtor_type_of_person'] ?? 'N') === 'J' ? 'selected' : '' ?>>J - Jurídica</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <!-- Transaction Configuration -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-gear"></i> Configuración de Transacción</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="transaction_type" class="form-label">Tipo de Transacción</label>
                                                    <input type="text" class="form-control" id="transaction_type" name="transaction_type"
                                                        value="<?= esc($config['transaction_type'] ?? '320') ?>">
                                                    <div class="form-text">Por defecto: 320</div>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="channel" class="form-label">Canal</label>
                                                    <input type="text" class="form-control" id="channel" name="channel"
                                                        value="<?= esc($config['channel'] ?? '15') ?>">
                                                    <div class="form-text">Por defecto: 15</div>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="creditor_address_line" class="form-label">Dirección del Acreedor</label>
                                                    <input type="text" class="form-control" id="creditor_address_line" name="creditor_address_line"
                                                        value="<?= esc($config['creditor_address_line'] ?? 'JR LIMA') ?>">
                                                    <div class="form-text">Por defecto: JR LIMA</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="<?= site_url('superadmin/ligo-config') ?>" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Cancelar
                                    </a>

                                    <div class="btn-group">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg"></i> Guardar Configuración
                                        </button>
                                        <button type="button" class="btn btn-outline-info" id="testConfigBtn"
                                            data-config-id="<?= $config['id'] ?>">
                                            <i class="bi bi-lightning"></i> Probar Configuración
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Results Modal -->
<div class="modal fade" id="testResultsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-lightning"></i> Resultados de la Prueba
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="testResultsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Probando configuración...</span>
                        </div>
                        <p class="mt-2">Probando configuración...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('togglePasswordIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });

        // Toggle webhook secret visibility
        document.getElementById('toggleWebhookSecret').addEventListener('click', function() {
            const webhookInput = document.getElementById('webhook_secret');
            const icon = document.getElementById('toggleWebhookSecretIcon');

            if (webhookInput.type === 'password') {
                webhookInput.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                webhookInput.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });

        // Test configuration
        document.getElementById('testConfigBtn').addEventListener('click', function() {
            const configId = this.getAttribute('data-config-id');
            const modal = new bootstrap.Modal(document.getElementById('testResultsModal'));

            // Reset modal content
            document.getElementById('testResultsContent').innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Probando configuración...</span>
                </div>
                <p class="mt-2">Probando configuración...</p>
            </div>
        `;

            modal.show();

            // Make test request
            fetch(`<?= site_url('superadmin/ligo-config/test') ?>/${configId}`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="X-CSRF-TOKEN"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    // Log complete response to console for debugging
                    console.log('🔍 Test Response:', data);

                    // Also log debug info if available
                    if (data.debug_info) {
                        console.log('🐛 Debug Info:', data.debug_info);
                        console.log('🌐 Full Auth Endpoint:', data.debug_info.full_auth_endpoint);
                    }

                    let content = '';

                    if (data.success) {
                        content = `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i>
                        <strong>✅ Prueba exitosa</strong><br>
                        ${data.message}
                    </div>
                `;

                        if (data.data) {
                            content += `
                        <h6>Detalles de la respuesta:</h6>
                        <pre class="bg-light p-3 rounded"><code>${JSON.stringify(data.data, null, 2)}</code></pre>
                    `;
                        }
                    } else {
                        content = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>❌ Error en la prueba</strong><br>
                        ${data.message}
                    </div>
                `;

                        // Add debug information if available
                        if (data.debug_info) {
                            content += `
                        <h6>Información de Debug:</h6>
                        <div class="bg-light p-3 rounded">
                            <small>
                                <strong>Entorno:</strong> ${data.debug_info.environment}<br>
                                <strong>Usuario:</strong> ${data.debug_info.username}<br>
                                <strong>Company ID:</strong> ${data.debug_info.company_id}<br>
                                <strong>URL de Auth:</strong> ${data.debug_info.actual_auth_url}<br>
                                <strong>URL de API:</strong> ${data.debug_info.actual_api_url}<br>
                                <strong>Endpoint Completo:</strong> <code>${data.debug_info.full_auth_endpoint}</code>
                            </small>
                        </div>
                    `;
                        }
                    }

                    document.getElementById('testResultsContent').innerHTML = content;
                })
                .catch(error => {
                    document.getElementById('testResultsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Error de conexión</strong><br>
                    ${error.message}
                </div>
            `;
                });
        });
    });
</script>
<?= $this->endSection() ?>