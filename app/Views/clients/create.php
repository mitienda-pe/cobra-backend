<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Nuevo Cliente<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Nuevo Cliente</h3>
                    <a href="<?= site_url('clients') ?>" class="btn btn-secondary">Volver</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (session('error')): ?>
                    <div class="alert alert-danger">
                        <?= session('error') ?>
                        <?php if (session('debug_error')): ?>
                            <hr>
                            <pre class="mb-0"><code><?= session('debug_error') ?></code></pre>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (session('message')): ?>
                    <div class="alert alert-success">
                        <?= session('message') ?>
                    </div>
                <?php endif; ?>
                
                <form id="clientForm" action="<?= site_url('clients/create') ?>" method="post">
                    <?= csrf_field() ?>
                    
                    <?php if (isset($organizations) && $auth->hasRole('superadmin')): ?>
                    <!-- Organization information -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Información de Organización</h6>
                                    <?php if ($auth->organizationId()): ?>
                                        <?php 
                                            $orgModel = new \App\Models\OrganizationModel();
                                            $org = $orgModel->find($auth->organizationId());
                                            $orgName = $org ? $org['name'] : 'Desconocida';
                                        ?>
                                        <div class="alert alert-info mb-0">
                                            <i class="bi bi-building"></i> Creando cliente para: <strong><?= esc($orgName) ?></strong>
                                        </div>
                                        <input type="hidden" name="organization_id" value="<?= $auth->organizationId() ?>">
                                    <?php else: ?>
                                        <div class="form-group">
                                            <label for="organization_id" class="form-label">Organización *</label>
                                            <select class="form-select <?= session('errors.organization_id') ? 'is-invalid' : '' ?>" id="organization_id" name="organization_id" required>
                                                <option value="">Seleccionar organización</option>
                                                <?php foreach ($organizations as $org): ?>
                                                    <option value="<?= $org['id'] ?>" <?= old('organization_id') == $org['id'] ? 'selected' : '' ?>>
                                                        <?= esc($org['name']) ?> <?= ($org['status'] == 'inactive') ? '(Inactiva)' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (session('errors.organization_id')): ?>
                                                <div class="invalid-feedback"><?= session('errors.organization_id') ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Client Information -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="business_name" class="form-label">Nombre Comercial *</label>
                            <input type="text" class="form-control <?= session('errors.business_name') ? 'is-invalid' : '' ?>" id="business_name" name="business_name" value="<?= old('business_name') ?>" required>
                            <?php if (session('errors.business_name')): ?>
                                <div class="invalid-feedback"><?= session('errors.business_name') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="legal_name" class="form-label">Razón Social *</label>
                            <input type="text" class="form-control <?= session('errors.legal_name') ? 'is-invalid' : '' ?>" id="legal_name" name="legal_name" value="<?= old('legal_name') ?>" required>
                            <?php if (session('errors.legal_name')): ?>
                                <div class="invalid-feedback"><?= session('errors.legal_name') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="document_number" class="form-label">RUC *</label>
                            <input type="text" class="form-control <?= session('errors.document_number') ? 'is-invalid' : '' ?>" id="document_number" name="document_number" value="<?= old('document_number') ?>" required>
                            <?php if (session('errors.document_number')): ?>
                                <div class="invalid-feedback"><?= session('errors.document_number') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="external_id" class="form-label">ID Externo</label>
                            <input type="text" class="form-control <?= session('errors.external_id') ? 'is-invalid' : '' ?>" id="external_id" name="external_id" value="<?= old('external_id') ?>">
                            <?php if (session('errors.external_id')): ?>
                                <div class="invalid-feedback"><?= session('errors.external_id') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="contact_name" class="form-label">Nombre de Contacto</label>
                            <input type="text" class="form-control <?= session('errors.contact_name') ? 'is-invalid' : '' ?>" id="contact_name" name="contact_name" value="<?= old('contact_name') ?>">
                            <?php if (session('errors.contact_name')): ?>
                                <div class="invalid-feedback"><?= session('errors.contact_name') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contact_phone" class="form-label">Teléfono de Contacto</label>
                            <input type="text" class="form-control <?= session('errors.contact_phone') ? 'is-invalid' : '' ?>" id="contact_phone" name="contact_phone" value="<?= old('contact_phone') ?>">
                            <?php if (session('errors.contact_phone')): ?>
                                <div class="invalid-feedback"><?= session('errors.contact_phone') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="address" class="form-label">Dirección</label>
                            <input type="text" class="form-control <?= session('errors.address') ? 'is-invalid' : '' ?>" id="address" name="address" value="<?= old('address') ?>">
                            <?php if (session('errors.address')): ?>
                                <div class="invalid-feedback"><?= session('errors.address') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ubigeo" class="form-label">Ubigeo</label>
                            <input type="text" class="form-control <?= session('errors.ubigeo') ? 'is-invalid' : '' ?>" id="ubigeo" name="ubigeo" value="<?= old('ubigeo') ?>">
                            <?php if (session('errors.ubigeo')): ?>
                                <div class="invalid-feedback"><?= session('errors.ubigeo') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="zip_code" class="form-label">Código Postal</label>
                            <input type="text" class="form-control <?= session('errors.zip_code') ? 'is-invalid' : '' ?>" id="zip_code" name="zip_code" value="<?= old('zip_code') ?>">
                            <?php if (session('errors.zip_code')): ?>
                                <div class="invalid-feedback"><?= session('errors.zip_code') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="latitude" class="form-label">Latitud</label>
                            <input type="text" class="form-control <?= session('errors.latitude') ? 'is-invalid' : '' ?>" id="latitude" name="latitude" value="<?= old('latitude') ?>">
                            <?php if (session('errors.latitude')): ?>
                                <div class="invalid-feedback"><?= session('errors.latitude') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="longitude" class="form-label">Longitud</label>
                            <input type="text" class="form-control <?= session('errors.longitude') ? 'is-invalid' : '' ?>" id="longitude" name="longitude" value="<?= old('longitude') ?>">
                            <?php if (session('errors.longitude')): ?>
                                <div class="invalid-feedback"><?= session('errors.longitude') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (isset($portfolios) && !empty($portfolios)): ?>
                    <div class="mb-3">
                        <label class="form-label">Carteras de Cobro</label>
                        <div>
                            <?php foreach ($portfolios as $portfolio): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="portfolio_ids[]" id="portfolio_<?= $portfolio['id'] ?>" value="<?= $portfolio['id'] ?>" <?= old('portfolio_ids') && in_array($portfolio['id'], old('portfolio_ids')) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="portfolio_<?= $portfolio['id'] ?>"><?= $portfolio['name'] ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const form = document.getElementById('clientForm');
                    const submitButton = document.querySelector('button[type="submit"]');
                    const errorMessage = document.getElementById('errorMessage');
                    const successMessage = document.getElementById('successMessage');
                    
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        // Reset error states
                        errorMessage.classList.add('d-none');
                        successMessage.classList.add('d-none');
                        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                        
                        // Show loading state
                        submitButton.disabled = true;
                        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';
                        
                        // Get form data
                        const formData = new FormData(form);
                        
                        // No need to add CSRF token in headers - it's already in the formData
                        // AJAX submission
                        fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            submitButton.disabled = false;
                            submitButton.textContent = 'Guardar';
                            
                            if (data.success) {
                                // Show success message
                                successMessage.textContent = data.message;
                                successMessage.classList.remove('d-none');
                                
                                // Redirect after a short delay
                                setTimeout(() => {
                                    window.location.href = data.redirect;
                                }, 1000);
                            } else {
                                // Handle validation errors
                                if (data.errors) {
                                    // Display field-specific errors
                                    Object.keys(data.errors).forEach(field => {
                                        const input = document.querySelector(`[name="${field}"]`);
                                        if (input) {
                                            input.classList.add('is-invalid');
                                            const errorElement = document.querySelector(`[id="error-${field}"]`);
                                            if (errorElement) {
                                                errorElement.textContent = data.errors[field];
                                            }
                                        }
                                    });
                                }
                                
                                // Show general error message
                                errorMessage.textContent = data.error || 'Error al crear el cliente. Por favor intente nuevamente.';
                                errorMessage.classList.remove('d-none');
                                
                                // Scroll to error
                                errorMessage.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        })
                        .catch(error => {
                            submitButton.disabled = false;
                            submitButton.textContent = 'Guardar';
                            
                            // Show error message
                            errorMessage.textContent = 'Error de conexión. Por favor intente nuevamente.';
                            errorMessage.classList.remove('d-none');
                            console.error('Error:', error);
                        });
                    });
                });
                </script>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>