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
                <div id="errorMessage" class="alert alert-danger d-none"></div>
                <div id="successMessage" class="alert alert-success d-none"></div>
                
                <form id="clientForm" action="<?= site_url('clients') ?>" method="post">
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
                            <label for="name" class="form-label">Nombre *</label>
                            <input type="text" class="form-control <?= session('errors.name') ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= old('name') ?>" required>
                            <?php if (session('errors.name')): ?>
                                <div class="invalid-feedback"><?= session('errors.name') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="code" class="form-label">Código *</label>
                            <input type="text" class="form-control <?= session('errors.code') ? 'is-invalid' : '' ?>" id="code" name="code" value="<?= old('code') ?>" required>
                            <?php if (session('errors.code')): ?>
                                <div class="invalid-feedback"><?= session('errors.code') ?></div>
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
                            <label for="contact_email" class="form-label">Correo de Contacto</label>
                            <input type="email" class="form-control <?= session('errors.contact_email') ? 'is-invalid' : '' ?>" id="contact_email" name="contact_email" value="<?= old('contact_email') ?>">
                            <?php if (session('errors.contact_email')): ?>
                                <div class="invalid-feedback"><?= session('errors.contact_email') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="contact_phone" class="form-label">Teléfono de Contacto</label>
                            <input type="tel" class="form-control <?= session('errors.contact_phone') ? 'is-invalid' : '' ?>" id="contact_phone" name="contact_phone" value="<?= old('contact_phone') ?>">
                            <?php if (session('errors.contact_phone')): ?>
                                <div class="invalid-feedback"><?= session('errors.contact_phone') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Estado *</label>
                            <select class="form-select <?= session('errors.status') ? 'is-invalid' : '' ?>" id="status" name="status" required>
                                <option value="active" <?= old('status') == 'active' ? 'selected' : '' ?>>Activo</option>
                                <option value="inactive" <?= old('status') == 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                            <?php if (session('errors.status')): ?>
                                <div class="invalid-feedback"><?= session('errors.status') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control <?= session('errors.description') ? 'is-invalid' : '' ?>" id="description" name="description" rows="3"><?= old('description') ?></textarea>
                            <?php if (session('errors.description')): ?>
                                <div class="invalid-feedback"><?= session('errors.description') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Crear Cliente
                        </button>
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
                            submitButton.textContent = 'Crear Cliente';
                            
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
                            submitButton.textContent = 'Crear Cliente';
                            
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