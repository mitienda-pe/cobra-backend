<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Nueva Cuenta por Cobrar<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Nueva Cuenta por Cobrar</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= site_url('invoices') ?>">Cuentas por Cobrar</a></li>
                <li class="breadcrumb-item active">Nueva Cuenta</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form action="<?= site_url('invoices/create') ?>" method="post">
                    <?= csrf_field() ?>
                    
                    <?php if ($auth->hasRole('superadmin') && isset($organizations)): ?>
                        <?php if ($auth->organizationId()): ?>
                            <?php 
                                $orgModel = new \App\Models\OrganizationModel();
                                $org = $orgModel->find($auth->organizationId());
                                $orgName = $org ? $org['name'] : 'Desconocida';
                            ?>
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-building"></i> Creando cuenta por cobrar para: <strong><?= esc($orgName) ?></strong>
                            </div>
                            <input type="hidden" name="organization_id" value="<?= $auth->organizationId() ?>">
                        <?php else: ?>
                            <div class="mb-3">
                                <label for="organization_id" class="form-label">Organización *</label>
                                <select name="organization_id" id="organization_id" class="form-select" required>
                                    <option value="">Seleccione una organización</option>
                                    <?php foreach ($organizations as $org): ?>
                                        <option value="<?= $org['id'] ?>">
                                            <?= esc($org['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    <?php elseif (!$auth->hasRole('superadmin')): ?>
                        <!-- For non-superadmins, just add the hidden organization ID -->
                        <input type="hidden" name="organization_id" value="<?= $auth->organizationId() ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="client_id" class="form-label">Cliente *</label>
                        <div class="position-relative">
                            <select name="client_id" id="client_id" class="form-select" required <?= empty($clients) ? 'disabled' : '' ?>>
                                <option value="">Seleccione un cliente</option>
                                <?php if (!empty($clients)): ?>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client['id'] ?>" <?= old('client_id') == $client['id'] ? 'selected' : '' ?>>
                                            <?= esc($client['business_name']) ?> (<?= esc($client['document_number']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div id="client-loading" class="d-none position-absolute top-0 end-0 mt-2 me-3">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                            </div>
                        </div>
                        <?php if (empty($clients)): ?>
                            <div class="form-text text-warning">No hay clientes disponibles para la organización seleccionada.</div>
                        <?php endif; ?>
                        <div id="client-error" class="form-text text-danger d-none"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="invoice_number" class="form-label">Número de Factura *</label>
                        <input type="text" class="form-control" id="invoice_number" name="invoice_number" 
                               value="<?= old('invoice_number') ?>" required maxlength="50">
                    </div>
                    
                    <div class="mb-3">
                        <label for="concept" class="form-label">Concepto *</label>
                        <input type="text" class="form-control" id="concept" name="concept" 
                               value="<?= old('concept') ?>" required maxlength="255">
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Monto *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   value="<?= old('amount') ?>" required step="0.01" min="0.01">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="due_date" class="form-label">Fecha de Vencimiento *</label>
                        <input type="date" class="form-control" id="due_date" name="due_date" 
                               value="<?= old('due_date') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="external_id" class="form-label">ID Externo (Opcional)</label>
                        <input type="text" class="form-control" id="external_id" name="external_id" 
                               value="<?= old('external_id') ?>" maxlength="36">
                        <div class="form-text">ID de referencia en sistema externo.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notas (Opcional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= old('notes') ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?= site_url('invoices') ?>" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                Información
            </div>
            <div class="card-body">
                <p>Complete todos los campos requeridos marcados con *.</p>
                <p>El estado inicial será <strong>Pendiente</strong>.</p>
                <p>Una vez creada la cuenta por cobrar, podrá registrar pagos sobre ella.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle client loading for organization selection
    const organizationSelect = document.getElementById('organization_id');
    const clientSelect = document.getElementById('client_id');
    const clientLoading = document.getElementById('client-loading');
    const clientError = document.getElementById('client-error');
    
    // Only set up event handlers if organization select exists and we're not using the global organization selector
    if (organizationSelect && !document.querySelector('input[type="hidden"][name="organization_id"]')) {
        organizationSelect.addEventListener('change', function() {
            const organizationId = this.value;
            
            if (organizationId) {
                // Mostrar indicador de carga
                clientSelect.disabled = true;
                clientLoading.classList.remove('d-none');
                clientError.classList.add('d-none');
                clientSelect.innerHTML = '<option value="">Cargando clientes...</option>';
                
                // Realizar petición AJAX para obtener clientes
                fetch('<?= site_url('debug/get-clients-by-organization') ?>/' + organizationId)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la respuesta: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Ocultar indicador de carga
                        clientLoading.classList.add('d-none');
                        
                        // Verificar si hay error en la respuesta
                        if (data.error) {
                            clientError.textContent = data.error;
                            clientError.classList.remove('d-none');
                            clientSelect.innerHTML = '<option value="">Error al cargar clientes</option>';
                            clientSelect.disabled = true;
                            return;
                        }
                        
                        // Actualizar el select de clientes
                        clientSelect.innerHTML = '<option value="">Seleccione un cliente</option>';
                        
                        if (data.length > 0) {
                            data.forEach(client => {
                                const option = document.createElement('option');
                                option.value = client.id;
                                option.textContent = `${client.business_name} (${client.document_number})`;
                                clientSelect.appendChild(option);
                            });
                            clientSelect.disabled = false;
                        } else {
                            clientSelect.innerHTML = '<option value="">No hay clientes disponibles</option>';
                            clientSelect.disabled = true;
                        }
                    })
                    .catch(error => {
                        // Ocultar indicador de carga y mostrar error
                        clientLoading.classList.add('d-none');
                        clientError.textContent = 'Error al cargar clientes: ' + error.message;
                        clientError.classList.remove('d-none');
                        console.error('Error al cargar clientes:', error);
                        clientSelect.innerHTML = '<option value="">Error al cargar clientes</option>';
                        clientSelect.disabled = true;
                    });
            } else {
                // Si no se selecciona organización, vaciar y deshabilitar el select de clientes
                clientLoading.classList.add('d-none');
                clientError.classList.add('d-none');
                clientSelect.innerHTML = '<option value="">Seleccione una organización primero</option>';
                clientSelect.disabled = true;
            }
        });
    }
});
</script>
<?= $this->endSection() ?>