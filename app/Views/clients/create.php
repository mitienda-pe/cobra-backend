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
                                            <select class="form-select" id="organization_id" name="organization_id" required>
                                                <option value="">Seleccione una organización</option>
                                                <?php foreach ($organizations as $org): ?>
                                                    <option value="<?= $org['id'] ?>">
                                                        <?= esc($org['name']) ?> <?= ($org['status'] == 'inactive') ? '(Inactiva)' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback" id="error-organization_id"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php elseif (!$auth->hasRole('superadmin')): ?>
                    <!-- For non-superadmins, just add the hidden organization ID -->
                    <input type="hidden" name="organization_id" value="<?= $auth->organizationId() ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="business_name" class="form-label">Nombre Comercial *</label>
                            <input type="text" class="form-control" id="business_name" name="business_name" value="<?= old('business_name') ?>" required>
                            <div class="invalid-feedback" id="error-business_name"></div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="legal_name" class="form-label">Razón Social *</label>
                            <input type="text" class="form-control" id="legal_name" name="legal_name" value="<?= old('legal_name') ?>" required>
                            <div class="invalid-feedback" id="error-legal_name"></div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="document_number" class="form-label">RUC/Documento *</label>
                            <input type="text" class="form-control" id="document_number" name="document_number" value="<?= old('document_number') ?>" required>
                            <div class="invalid-feedback" id="error-document_number"></div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="external_id" class="form-label">ID Externo (para integración)</label>
                            <input type="text" class="form-control" id="external_id" name="external_id" value="<?= old('external_id') ?>" placeholder="Dejar en blanco para generar automáticamente">
                        </div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">Información de Contacto</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="contact_name" class="form-label">Nombre de Contacto</label>
                            <input type="text" class="form-control" id="contact_name" name="contact_name" value="<?= old('contact_name') ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="contact_phone" class="form-label">Teléfono de Contacto</label>
                            <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?= old('contact_phone') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Dirección</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?= old('address') ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="ubigeo" class="form-label">Ubigeo</label>
                            <input type="text" class="form-control" id="ubigeo" name="ubigeo" value="<?= old('ubigeo') ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="zip_code" class="form-label">Código Postal</label>
                            <input type="text" class="form-control" id="zip_code" name="zip_code" value="<?= old('zip_code') ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="latitude" class="form-label">Latitud</label>
                            <input type="text" class="form-control" id="latitude" name="latitude" value="<?= old('latitude') ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="longitude" class="form-label">Longitud</label>
                            <input type="text" class="form-control" id="longitude" name="longitude" value="<?= old('longitude') ?>">
                        </div>
                    </div>
                    
                    <!-- Se eliminó la sección de carteras ya que los clientes se asignarán automáticamente -->
                    
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" id="submitButton" class="btn btn-primary">Guardar Cliente</button>
                    </div>
                </form>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const form = document.getElementById('clientForm');
                    const submitButton = document.getElementById('submitButton');
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
                            submitButton.textContent = 'Guardar Cliente';
                            
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
                                            const errorElement = document.getElementById(`error-${field}`);
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
                            submitButton.textContent = 'Guardar Cliente';
                            
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