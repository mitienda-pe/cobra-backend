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
                            <h5 class="mb-0">Configuración de Pagos Ligo QR</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="ligo_enabled" name="ligo_enabled" value="1" <?= old('ligo_enabled', $organization['ligo_enabled'] ?? 0) == 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="ligo_enabled">Habilitar pagos con QR de Ligo</label>
                            </div>
                            
                            <div class="mb-3">
                                <label for="ligo_username" class="form-label">Nombre de Usuario (Ligo)</label>
                                <input type="text" class="form-control <?= session('errors.ligo_username') ? 'is-invalid' : '' ?>" id="ligo_username" name="ligo_username" value="<?= old('ligo_username', $organization['ligo_username'] ?? '') ?>">
                                <?php if (session('errors.ligo_username')): ?>
                                    <div class="invalid-feedback"><?= session('errors.ligo_username') ?></div>
                                <?php endif; ?>
                                <div class="form-text">Nombre de usuario asignado por Ligo</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="ligo_password" class="form-label">Contraseña (Ligo)</label>
                                <input type="password" class="form-control <?= session('errors.ligo_password') ? 'is-invalid' : '' ?>" id="ligo_password" name="ligo_password" value="<?= old('ligo_password', $organization['ligo_password'] ?? '') ?>">
                                <?php if (session('errors.ligo_password')): ?>
                                    <div class="invalid-feedback"><?= session('errors.ligo_password') ?></div>
                                <?php endif; ?>
                                <div class="form-text">Si no desea cambiar la contraseña, deje este campo en blanco.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="ligo_company_id" class="form-label">ID de Empresa (Ligo)</label>
                                <input type="text" class="form-control <?= session('errors.ligo_company_id') ? 'is-invalid' : '' ?>" id="ligo_company_id" name="ligo_company_id" value="<?= old('ligo_company_id', $organization['ligo_company_id'] ?? '') ?>">
                                <?php if (session('errors.ligo_company_id')): ?>
                                    <div class="invalid-feedback"><?= session('errors.ligo_company_id') ?></div>
                                <?php endif; ?>
                                <div class="form-text">Código de identificación única de la empresa proporcionado por Ligo</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="ligo_private_key" class="form-label">Llave Privada (Ligo)</label>
                                <textarea class="form-control <?= session('errors.ligo_private_key') ? 'is-invalid' : '' ?>" id="ligo_private_key" name="ligo_private_key" rows="5"><?= old('ligo_private_key', $organization['ligo_private_key'] ?? '') ?></textarea>
                                <?php if (session('errors.ligo_private_key')): ?>
                                    <div class="invalid-feedback"><?= session('errors.ligo_private_key') ?></div>
                                <?php endif; ?>
                                <div class="form-text">Llave privada proporcionada por Ligo para firmar tokens de autenticación</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="ligo_webhook_secret" class="form-label">Webhook Secret (Ligo)</label>
                                <input type="password" class="form-control <?= session('errors.ligo_webhook_secret') ? 'is-invalid' : '' ?>" id="ligo_webhook_secret" name="ligo_webhook_secret" value="<?= old('ligo_webhook_secret', $organization['ligo_webhook_secret'] ?? '') ?>">
                                <?php if (session('errors.ligo_webhook_secret')): ?>
                                    <div class="invalid-feedback"><?= session('errors.ligo_webhook_secret') ?></div>
                                <?php endif; ?>
                                <div class="form-text">Secret para validar las notificaciones de webhook de Ligo</div>
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
<?= $this->endSection() ?>