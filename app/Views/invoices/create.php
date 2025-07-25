<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Nueva Factura<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Nueva Factura</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= site_url('invoices') ?>">Facturas</a></li>
                <li class="breadcrumb-item active">Nueva Factura</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
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
                                <i class="bi bi-building"></i> Creando factura para: <strong><?= esc($orgName) ?></strong>
                            </div>
                            <input type="hidden" name="organization_id" value="<?= $auth->organizationId() ?>">
                        <?php else: ?>
                            <div class="mb-3">
                                <label for="organization_id" class="form-label">Organización *</label>
                                <select name="organization_id" id="organization_id" class="form-select" required>
                                    <option value="">Seleccione una organización</option>
                                    <?php foreach ($organizations as $org): ?>
                                        <option value="<?= $org['id'] ?>" data-uuid="<?= $org['uuid'] ?>">
                                            <?= esc($org['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    <?php elseif (!$auth->hasRole('superadmin')): ?>
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
                            <div id="initial-client-warning" class="form-text text-warning">No hay clientes disponibles para la organización seleccionada.</div>
                        <?php endif; ?>
                        <div id="client-error" class="form-text text-danger d-none"></div>
                        <div id="client-empty" class="form-text text-warning d-none"></div>
                    </div>

                    <div class="mb-3">
                        <label for="number" class="form-label">Número de Factura *</label>
                        <input type="text" class="form-control" id="number" name="number" 
                               value="<?= old('number') ?>" required maxlength="50">
                    </div>

                    <div class="mb-3">
                        <label for="concept" class="form-label">Concepto *</label>
                        <input type="text" class="form-control" id="concept" name="concept" 
                               value="<?= old('concept') ?>" required maxlength="255">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="issue_date" class="form-label">Fecha de Emisión *</label>
                                <input type="date" class="form-control" id="issue_date" name="issue_date" 
                                       value="<?= old('issue_date', date('Y-m-d')) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="due_date" class="form-label" id="due_date_label">Fecha de Vencimiento *</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" 
                                       value="<?= old('due_date', date('Y-m-d', strtotime('+30 days'))) ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="external_id" class="form-label">ID Externo</label>
                                <input type="text" class="form-control" id="external_id" name="external_id" 
                                       value="<?= old('external_id') ?>" maxlength="36">
                                <div class="form-text">ID opcional para integración con otros sistemas</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="num_instalments" class="form-label">Número de Cuotas *</label>
                                <select class="form-select" id="num_instalments" name="num_instalments" required>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?= $i ?>" <?= old('num_instalments', 1) == $i ? 'selected' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="currency" class="form-label">Moneda *</label>
                                <select name="currency" id="currency" class="form-select" required>
                                    <option value="PEN" <?= old('currency') === 'PEN' ? 'selected' : '' ?>>Soles (PEN)</option>
                                    <option value="USD" <?= old('currency') === 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Importe Total *</label>
                                <div class="input-group">
                                    <span class="input-group-text currency-symbol">S/</span>
                                    <input type="number" class="form-control" id="amount" name="total_amount" 
                                           value="<?= old('total_amount') ?>" required step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="instalment_interval_container" class="mb-3" style="display: none;">
                        <label for="instalment_interval" class="form-label">Intervalo entre cuotas (días) *</label>
                        <input type="number" class="form-control" id="instalment_interval" name="instalment_interval" 
                               value="<?= old('instalment_interval', 30) ?>" min="1" max="90">
                        <div id="instalment_preview" class="alert alert-info mt-2" style="display: none;">
                            <p class="mb-0">
                                <strong>Monto por cuota:</strong> <span id="instalment_amount">Calculando...</span>
                                <br>
                                <small class="text-muted">El monto de la última cuota puede variar ligeramente para ajustar el total.</small>
                            </p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Notas</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= old('notes') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= site_url('invoices') ?>" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Crear Factura</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Ayuda</h5>
            </div>
            <div class="card-body">
                <p>Los campos marcados con <strong>*</strong> son obligatorios.</p>
                <p>El número de factura debe ser único dentro de cada organización.</p>
                <p>La moneda seleccionada determinará el símbolo mostrado en el importe.</p>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const currencySelect = document.getElementById('currency');
    const currencySymbol = document.querySelector('.currency-symbol');
    
    function updateCurrencySymbol() {
        currencySymbol.textContent = currencySelect.value === 'PEN' ? 'S/' : '$';
    }
    
    currencySelect.addEventListener('change', updateCurrencySymbol);
    updateCurrencySymbol();

    // Manejo de cuotas
    const numInstalmentsSelect = document.getElementById('num_instalments');
    const instalmentIntervalContainer = document.getElementById('instalment_interval_container');
    const instalmentPreview = document.getElementById('instalment_preview');
    const dueDateLabel = document.getElementById('due_date_label');
    const amountInput = document.getElementById('amount');
    const instalmentAmountSpan = document.getElementById('instalment_amount');
    
    function toggleInstalmentFields() {
        const numInstalments = parseInt(numInstalmentsSelect.value);
        
        if (numInstalments > 1) {
            instalmentIntervalContainer.style.display = 'block';
            instalmentPreview.style.display = 'block';
            dueDateLabel.textContent = 'Fecha de Vencimiento de la Primera Cuota *';
        } else {
            instalmentIntervalContainer.style.display = 'none';
            instalmentPreview.style.display = 'none';
            dueDateLabel.textContent = 'Fecha de Vencimiento *';
        }
        
        updateInstalmentAmount();
    }
    
    function updateInstalmentAmount() {
        const totalAmount = parseFloat(amountInput.value) || 0;
        const numInstalments = parseInt(numInstalmentsSelect.value) || 1;
        const symbol = currencySelect.value === 'PEN' ? 'S/ ' : '$ ';
        
        if (totalAmount > 0 && numInstalments > 0) {
            const instalmentAmount = (totalAmount / numInstalments).toFixed(2);
            instalmentAmountSpan.textContent = symbol + instalmentAmount;
        } else {
            instalmentAmountSpan.textContent = symbol + '0.00';
        }
    }
    
    numInstalmentsSelect.addEventListener('change', toggleInstalmentFields);
    amountInput.addEventListener('input', updateInstalmentAmount);
    currencySelect.addEventListener('change', updateInstalmentAmount);
    
    // Inicializar campos de cuotas
    toggleInstalmentFields();

    // Handle client loading for organization
    const organizationSelect = document.getElementById('organization_id');
    if (organizationSelect) {
        organizationSelect.addEventListener('change', function() {
            const clientSelect = document.getElementById('client_id');
            const clientLoading = document.getElementById('client-loading');
            const clientError = document.getElementById('client-error');
            const clientEmpty = document.getElementById('client-empty');
            const initialWarning = document.getElementById('initial-client-warning');
            
            if (!this.value) {
                clientSelect.innerHTML = '<option value="">Seleccione un cliente</option>';
                clientSelect.disabled = true;
                clientError.classList.add('d-none');
                clientEmpty.classList.add('d-none');
                if (initialWarning) initialWarning.classList.remove('d-none');
                return;
            }
            
            clientSelect.disabled = true;
            clientLoading.classList.remove('d-none');
            clientError.classList.add('d-none');
            clientEmpty.classList.add('d-none');
            if (initialWarning) initialWarning.classList.add('d-none');
            
            // Obtener el UUID de la organización del atributo data-uuid
            const organizationUuid = organizationSelect.options[organizationSelect.selectedIndex].getAttribute('data-uuid');
            
            fetch(`<?= site_url('invoices') ?>/organization/${organizationUuid}/clients`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    clientSelect.innerHTML = '<option value="">Seleccione un cliente</option>';
                    
                    if (data.status === 'error') {
                        clientEmpty.textContent = data.message;
                        clientEmpty.classList.remove('d-none');
                        clientSelect.disabled = true;
                        return;
                    }
                    
                    if (!data.clients || data.clients.length === 0) {
                        clientEmpty.textContent = 'No hay clientes disponibles para la organización seleccionada.';
                        clientEmpty.classList.remove('d-none');
                        clientSelect.disabled = true;
                        return;
                    }
                    
                    data.clients.forEach(client => {
                        const option = document.createElement('option');
                        option.value = client.id;
                        option.textContent = `${client.business_name} (${client.document_number})`;
                        clientSelect.appendChild(option);
                    });
                    clientSelect.disabled = false;
                    if (initialWarning) initialWarning.classList.add('d-none');
                })
                .catch(error => {
                    console.error('Error:', error);
                    clientError.textContent = 'Error al cargar los clientes. Por favor, intente nuevamente.';
                    clientError.classList.remove('d-none');
                    clientSelect.innerHTML = '<option value="">Error al cargar clientes</option>';
                    clientSelect.disabled = true;
                })
                .finally(() => {
                    clientLoading.classList.add('d-none');
                });
        });
    }
});
</script>
<?= $this->endSection() ?>