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
                    <h3 class="card-title">Transferencia Ordinaria Ligo</h3>
                    <div class="card-tools">
                        <a href="<?= base_url('backoffice') ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Información</h6>
                        <p class="mb-0">La transferencia ordinaria se ejecuta en 4 pasos con confirmación del usuario: consulta de cuenta, obtención de respuesta, cálculo de comisión y ejecución final.</p>
                    </div>
                    
                    <!-- Progress Steps -->
                    <div class="row mb-4" id="progressSteps" style="display: none;">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Progreso de la Transferencia</h6>
                                    <div class="row text-center">
                                        <div class="col-3">
                                            <div class="step" id="step1">
                                                <div class="step-icon"><i class="fas fa-search"></i></div>
                                                <div class="step-text">Consultar Cuenta</div>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="step" id="step2">
                                                <div class="step-icon"><i class="fas fa-reply"></i></div>
                                                <div class="step-text">Verificar Datos</div>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="step" id="step3">
                                                <div class="step-icon"><i class="fas fa-calculator"></i></div>
                                                <div class="step-text">Calcular Comisión</div>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="step" id="step4">
                                                <div class="step-icon"><i class="fas fa-paper-plane"></i></div>
                                                <div class="step-text">Ejecutar</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form id="transferForm">
                        <?= csrf_field() ?>
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Datos del Deudor (Cuenta Superadmin)</h5>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    <strong>Transferencia desde cuenta centralizada</strong><br>
                                    Los datos del deudor se toman automáticamente de la configuración de Ligo del superadmin.
                                </div>
                                <?php if (isset($superadminConfig) && $superadminConfig): ?>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Configuración Activa</h6>
                                        <p class="card-text">
                                            <strong>Entorno:</strong> <span class="badge bg-<?= $superadminConfig['environment'] === 'prod' ? 'danger' : 'warning' ?>"><?= strtoupper($superadminConfig['environment']) ?></span><br>
                                            <strong>Company ID:</strong> <?= esc($superadminConfig['company_id']) ?><br>
                                            <strong>Account ID:</strong> <?= esc($superadminConfig['account_id']) ?>
                                        </p>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    No hay configuración de Ligo disponible. Configure las credenciales en 
                                    <a href="<?= site_url('superadmin/ligo-config') ?>">Configuración Ligo</a>.
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <h5>Datos del Acreedor y Transferencia</h5>
                                
                                <?php if (isset($is_general_view) && $is_general_view): ?>
                                    <!-- Vista general: selector de organizaciones -->
                                    <div class="form-group">
                                        <label for="organization_id">Seleccionar Organización Destino *</label>
                                        <select class="form-control" id="organization_id" name="organization_id" required onchange="updateOrganizationData()">
                                            <option value="">-- Seleccione una organización --</option>
                                            <?php if (isset($organizations) && is_array($organizations)): ?>
                                                <?php foreach ($organizations as $org): ?>
                                                    <option value="<?= $org['id'] ?>" 
                                                            data-cci="<?= esc($org['cci']) ?>" 
                                                            data-name="<?= esc($org['name']) ?>"
                                                            data-code="<?= esc($org['code']) ?>">
                                                        <?= esc($org['name']) ?> (<?= esc($org['code']) ?>) - <?= esc($org['cci']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <small class="form-text text-muted">Seleccione la organización que recibirá la transferencia</small>
                                    </div>
                                    
                                    <!-- Balance Disponible -->
                                    <div class="alert alert-info">
                                        <i class="fas fa-wallet"></i> 
                                        <strong>Balance Disponible:</strong>
                                        <button type="button" class="btn btn-sm btn-outline-primary float-right" id="refreshBalanceBtn" onclick="refreshBalance()">
                                            <i class="fas fa-sync-alt"></i> Actualizar Balance
                                        </button>
                                        <br>
                                        <span class="h5 text-primary" id="availableBalance">--</span>
                                        <small class="text-muted d-block" id="balanceStatus">Haz clic en "Actualizar Balance" para obtener el saldo actual</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="creditorCCI">CCI del Acreedor *</label>
                                        <input type="text" class="form-control" id="creditorCCI" name="creditorCCI" 
                                               maxlength="20" readonly required placeholder="Seleccione una organización primero">
                                        <small class="form-text text-muted" id="organizationInfo">Seleccione una organización para ver su CCI</small>
                                    </div>
                                    
                                <?php else: ?>
                                    <!-- Vista específica de organización -->
                                    <div class="alert alert-success">
                                        <i class="fas fa-building"></i> 
                                        <strong>Organización Destino:</strong><br>
                                        <?= esc($organization['name']) ?> (<?= esc($organization['code']) ?>)
                                    </div>
                                    
                                    <!-- Balance Disponible -->
                                    <div class="alert alert-info">
                                        <i class="fas fa-wallet"></i> 
                                        <strong>Balance Disponible:</strong><br>
                                        <span class="h5 text-primary" id="availableBalance">S/. <?= number_format($accountBalance ?? 0, 2) ?></span>
                                        <small class="text-muted d-block">Solo puedes transferir hasta este monto</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="creditorCCI">CCI del Acreedor *</label>
                                        <input type="text" class="form-control" id="creditorCCI" name="creditorCCI" 
                                               value="<?= esc($organization['cci']) ?>" maxlength="20" readonly required>
                                        <small class="form-text text-muted">CCI de 20 dígitos de la organización: <?= esc($organization['name']) ?></small>
                                    </div>
                                <?php endif; ?>
                                <div class="form-group">
                                    <label for="amount">Monto *</label>
                                    <?php if (isset($is_general_view) && $is_general_view): ?>
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                               step="0.01" min="0.01" max="999999" required>
                                        <small class="form-text text-muted" id="maxAmountText">Actualiza el balance para ver el monto máximo disponible</small>
                                    <?php else: ?>
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                               step="0.01" min="0.01" max="<?= $accountBalance ?? 0 ?>" required>
                                        <small class="form-text text-muted">Máximo disponible: S/. <?= number_format($accountBalance ?? 0, 2) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label for="currency">Moneda *</label>
                                    <select class="form-control" id="currency" name="currency" required>
                                        <option value="PEN">PEN - Soles</option>
                                        <option value="USD">USD - Dólares</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="unstructuredInformation">Concepto de la Transferencia</label>
                                    <?php if (isset($is_general_view) && $is_general_view): ?>
                                        <textarea class="form-control" id="unstructuredInformation" name="unstructuredInformation" 
                                                  rows="3" placeholder="Seleccione una organización primero"></textarea>
                                    <?php else: ?>
                                        <textarea class="form-control" id="unstructuredInformation" name="unstructuredInformation" 
                                                  rows="3" placeholder="Pago de comisiones a <?= esc($organization['code']) ?>">Pago de comisiones a <?= esc($organization['code']) ?></textarea>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg" id="startTransferBtn">
                                <i class="fas fa-play"></i> Iniciar Proceso de Transferencia
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg ml-2" onclick="clearTransferForm()">
                                <i class="fas fa-eraser"></i> Limpiar Formulario
                            </button>
                        </div>
                    </form>
                    
                    <!-- Confirmation Area -->
                    <div id="confirmationArea" style="display: none;">
                        <hr>
                        <div class="card border-warning">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Confirmar Transferencia</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Datos de la Transferencia</h6>
                                        <p><strong>Cuenta Destino:</strong> <span id="confirmCreditorName"></span></p>
                                        <p><strong>CCI:</strong> <span id="confirmCreditorCCI"></span></p>
                                        <p><strong>Monto:</strong> <span id="confirmAmount"></span></p>
                                        <p><strong>Moneda:</strong> <span id="confirmCurrency"></span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Comisiones y Total</h6>
                                        <p><strong>Comisión:</strong> <span id="confirmFeeAmount"></span></p>
                                        <p><strong>Total a Debitar:</strong> <span id="confirmTotalAmount" class="text-danger font-weight-bold"></span></p>
                                        <p><strong>Código de Comisión:</strong> <span id="confirmFeeCode"></span></p>
                                    </div>
                                </div>
                                <div class="text-center mt-3">
                                    <button class="btn btn-success btn-lg" onclick="executeTransfer()">
                                        <i class="fas fa-check"></i> Confirmar y Ejecutar Transferencia
                                    </button>
                                    <button class="btn btn-danger btn-lg ml-2" onclick="cancelTransfer()">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="loading" class="text-center" style="display: none;">
                        <div class="spinner-border text-warning" role="status">
                            <span class="sr-only">Procesando...</span>
                        </div>
                        <p class="mt-2">Procesando transferencia...</p>
                        <div class="progress mt-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%" id="progressBar"></div>
                        </div>
                        <small class="text-muted" id="progressText">Iniciando...</small>
                    </div>
                    
                    <div id="transferResult" style="display: none;">
                        <hr>
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle"></i> Transferencia Procesada Exitosamente</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>ID de Transferencia:</strong> <span id="resultTransferId"></span></p>
                                    <p><strong>ID de Consulta:</strong> <span id="resultAccountInquiryId"></span></p>
                                    <p><strong>Estado:</strong> <span id="resultStatus"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Monto:</strong> <span id="resultAmount"></span></p>
                                    <p><strong>Fecha:</strong> <span id="resultDate"></span></p>
                                </div>
                            </div>
                            <button class="btn btn-info btn-sm" onclick="showTransferDetails()">
                                <i class="fas fa-eye"></i> Ver Detalles Completos
                            </button>
                        </div>
                        
                        <div id="transferDetails" style="display: none;">
                            <div class="card">
                                <div class="card-header">
                                    <h6>Detalles del Proceso (4 Pasos)</h6>
                                    <small class="text-muted">Información técnica de la transferencia enviada y recibida de Ligo</small>
                                </div>
                                <div class="card-body">
                                    <div class="accordion" id="stepsAccordion">
                                        <div class="card">
                                            <div class="card-header" id="stepDetail1Header">
                                                <button class="btn btn-link w-100 text-left" type="button" data-toggle="collapse" data-target="#stepDetail1">
                                                    <i class="fas fa-search text-primary"></i> Paso 1: Consulta de Cuenta
                                                    <i class="fas fa-chevron-down float-right"></i>
                                                </button>
                                            </div>
                                            <div id="stepDetail1" class="collapse" data-parent="#stepsAccordion">
                                                <div class="card-body">
                                                    <h6>Datos enviados a Ligo:</h6>
                                                    <pre id="step1Details" class="bg-light p-2 rounded"></pre>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <div class="card-header" id="stepDetail2Header">
                                                <button class="btn btn-link w-100 text-left" type="button" data-toggle="collapse" data-target="#stepDetail2">
                                                    <i class="fas fa-reply text-info"></i> Paso 2: Respuesta de Consulta
                                                    <i class="fas fa-chevron-down float-right"></i>
                                                </button>
                                            </div>
                                            <div id="stepDetail2" class="collapse" data-parent="#stepsAccordion">
                                                <div class="card-body">
                                                    <h6>Información obtenida de la cuenta:</h6>
                                                    <pre id="step2Details" class="bg-light p-2 rounded"></pre>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <div class="card-header" id="stepDetail3Header">
                                                <button class="btn btn-link w-100 text-left" type="button" data-toggle="collapse" data-target="#stepDetail3">
                                                    <i class="fas fa-percentage text-warning"></i> Paso 3: Cálculo de Comisión
                                                    <i class="fas fa-chevron-down float-right"></i>
                                                </button>
                                            </div>
                                            <div id="stepDetail3" class="collapse" data-parent="#stepsAccordion">
                                                <div class="card-body">
                                                    <h6>Comisiones calculadas por Ligo:</h6>
                                                    <pre id="step3Details" class="bg-light p-2 rounded"></pre>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <div class="card-header" id="stepDetail4Header">
                                                <button class="btn btn-link w-100 text-left" type="button" data-toggle="collapse" data-target="#stepDetail4">
                                                    <i class="fas fa-paper-plane text-success"></i> Paso 4: Ejecución de Transferencia
                                                    <i class="fas fa-chevron-down float-right"></i>
                                                </button>
                                            </div>
                                            <div id="stepDetail4" class="collapse" data-parent="#stepsAccordion">
                                                <div class="card-body">
                                                    <h6>Respuesta de ejecución de Ligo:</h6>
                                                    <pre id="step4Details" class="bg-light p-2 rounded"></pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="errorResult" class="alert alert-danger" style="display: none;">
                        <h6><i class="fas fa-exclamation-triangle"></i> Error en la Transferencia</h6>
                        <p id="errorMessage"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.step {
    padding: 20px 10px;
}

.step-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    color: #6c757d;
    font-size: 18px;
}

.step-text {
    font-size: 14px;
    color: #6c757d;
}

.step.active .step-icon {
    background: #007bff;
    color: white;
}

.step.active .step-text {
    color: #007bff;
    font-weight: bold;
}

.step.completed .step-icon {
    background: #28a745;
    color: white;
}

.step.completed .step-text {
    color: #28a745;
    font-weight: bold;
}

.step.error .step-icon {
    background: #dc3545;
    color: white;
}

.step.error .step-text {
    color: #dc3545;
    font-weight: bold;
}

/* Accordion styles */
#stepsAccordion .card {
    border: 1px solid #dee2e6;
    margin-bottom: 0.5rem;
}

#stepsAccordion .btn-link {
    color: #495057;
    text-decoration: none;
}

#stepsAccordion .btn-link:hover {
    color: #007bff;
    text-decoration: none;
}

#stepsAccordion .btn-link:focus {
    box-shadow: none;
}

#stepsAccordion pre {
    font-size: 12px;
    max-height: 400px;
    overflow-y: auto;
}
</style>

<script>
let transferData = {};
let stepData = {};

// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    const transferForm = document.getElementById('transferForm');
    const creditorCCIInput = document.getElementById('creditorCCI');
    
    if (transferForm) {
        transferForm.addEventListener('submit', function(e) {
            e.preventDefault();
            startTransferProcess();
        });
    }
    
    // Validar solo números en CCI
    if (creditorCCIInput) {
        creditorCCIInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
});

function startTransferProcess() {
    const formData = {
        creditorCCI: document.getElementById('creditorCCI').value,
        amount: document.getElementById('amount').value,
        currency: document.getElementById('currency').value,
        unstructuredInformation: document.getElementById('unstructuredInformation').value
    };
    
    // Add organization_id if we're in general view (superadmin with organization selector)
    const organizationSelect = document.getElementById('organization_id');
    if (organizationSelect && organizationSelect.value) {
        formData.organization_id = organizationSelect.value;
    }
    
    if (!validateForm(formData)) {
        return;
    }
    
    transferData = formData;
    
    // Check if this is a withdrawal (coming from balance page)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('type') === 'withdrawal') {
        transferData.transfer_type = 'withdrawal';
        transferData.unstructuredInformation = urlParams.get('unstructuredInformation') || formData.unstructuredInformation;
        // Pre-fill form if coming from withdrawal
        if (urlParams.get('amount')) {
            document.getElementById('amount').value = urlParams.get('amount');
            transferData.amount = urlParams.get('amount');
        }
        if (urlParams.get('currency')) {
            document.getElementById('currency').value = urlParams.get('currency');
            transferData.currency = urlParams.get('currency');
        }
    } else {
        transferData.transfer_type = 'regular';
    }
    
    showElement('progressSteps');
    hideElement('confirmationArea');
    hideElement('transferResult');
    hideElement('errorResult');
    resetSteps();
    
    // Step 1: Account Inquiry
    executeStep1();
}

function validateForm(formData) {
    if (!formData.creditorCCI || formData.creditorCCI.length !== 20) {
        alert('El CCI del acreedor debe tener exactamente 20 dígitos');
        return false;
    }
    
    const amount = parseFloat(formData.amount);
    const maxAmount = parseFloat(document.getElementById('amount').getAttribute('max'));
    
    if (!formData.amount || amount <= 0) {
        alert('El monto debe ser mayor a 0');
        return false;
    }
    
    if (amount > maxAmount) {
        alert(`El monto no puede exceder el balance disponible: S/. ${maxAmount.toFixed(2)}`);
        return false;
    }
    
    return true;
}

function resetSteps() {
    const steps = document.querySelectorAll('.step');
    steps.forEach(step => {
        step.classList.remove('active', 'completed', 'error');
    });
}

function setStepStatus(stepNumber, status) {
    const step = document.getElementById('step' + stepNumber);
    if (step) {
        step.classList.remove('active', 'completed', 'error');
        step.classList.add(status);
    }
}

function showElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.style.display = 'block';
    }
}

function hideElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.style.display = 'none';
    }
}

function setText(elementId, text) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = text;
    }
}

function scrollToElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function makeRequest(url, method, data) {
    console.log('🌐 makeRequest - URL:', url, 'Method:', method, 'Data:', data);
    
    // Refresh CSRF token before request
    const csrfToken = getCSRFToken();
    if (!csrfToken) {
        console.error('❌ No CSRF token available, refreshing page...');
        location.reload();
        return Promise.reject('No CSRF token');
    }
    
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                console.log('📡 Response - Status:', xhr.status, 'Text:', xhr.responseText);
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (xhr.status === 200) {
                        resolve(response);
                    } else {
                        console.error('❌ Request failed - Status:', xhr.status, 'Response:', response);
                        reject({ status: xhr.status, response: response });
                    }
                } catch (e) {
                    console.error('❌ JSON Parse error:', e, 'Raw response:', xhr.responseText);
                    reject({ status: xhr.status, response: { messages: { error: 'Error parsing response' } } });
                }
            }
        };
        
        // Convert data object to form data and add CSRF token
        const formData = new URLSearchParams();
        
        // Add CSRF token (already obtained above)
        console.log('🔐 Using CSRF Token:', csrfToken);
        formData.append('<?= csrf_token() ?>', csrfToken);
        
        // Add user data
        for (const key in data) {
            formData.append(key, data[key]);
        }
        
        xhr.send(formData.toString());
    });
}

function getCSRFToken() {
    // Try to get CSRF token from meta tag first
    const metaToken = document.querySelector('meta[name="<?= csrf_token() ?>"]');
    if (metaToken) {
        console.log('🔐 CSRF token found in meta tag:', metaToken.getAttribute('content'));
        return metaToken.getAttribute('content');
    }
    
    // Try to get from hidden input field
    const inputToken = document.querySelector('input[name="<?= csrf_token() ?>"]');
    if (inputToken) {
        console.log('🔐 CSRF token found in input field:', inputToken.value);
        return inputToken.value;
    }
    
    // Try to get from cookie
    const cookieName = '<?= csrf_token() ?>';
    const cookies = document.cookie.split(';');
    for (let cookie of cookies) {
        const [name, value] = cookie.trim().split('=');
        if (name === cookieName) {
            console.log('🔐 CSRF token found in cookie:', decodeURIComponent(value));
            return decodeURIComponent(value);
        }
    }
    
    console.error('❌ CSRF token not found anywhere!');
    return null;
}

function executeStep1() {
    setStepStatus(1, 'active');
    
    const step1Data = {
        creditorCCI: transferData.creditorCCI,
        currency: transferData.currency
    };
    
    console.log('🚀 STEP 1 - Datos enviados:', step1Data);
    console.log('📋 STEP 1 - transferData completo:', transferData);
    
    makeRequest('<?= base_url('backoffice/transfer/step1') ?>', 'POST', step1Data)
    .then(response => {
        console.log('✅ STEP 1 - Respuesta recibida:', response);
        if (response.success) {
            setStepStatus(1, 'completed');
            stepData.step1 = response.data;
            console.log('📦 STEP 1 - Data guardada:', stepData.step1);
            setTimeout(() => executeStep2(), 1000);
        } else {
            setStepStatus(1, 'error');
            console.error('❌ STEP 1 - Error:', response);
            showError('Error en Paso 1: ' + (response.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        setStepStatus(1, 'error');
        const errorMsg = error.response?.messages?.error || 'Error al consultar cuenta';
        showError('Error en Paso 1: ' + errorMsg);
    });
}

function executeStep2() {
    setStepStatus(2, 'active');
    
    const step2Data = {
        accountInquiryId: stepData.step1.accountInquiryId
    };
    
    console.log('⚡ STEP 2 - Datos enviados:', step2Data);
    console.log('📋 STEP 2 - stepData.step1:', stepData.step1);
    
    makeRequest('<?= base_url('backoffice/transfer/step2') ?>', 'POST', step2Data)
    .then(response => {
        console.log('✅ STEP 2 - Respuesta recibida:', response);
        if (response.success) {
            setStepStatus(2, 'completed');
            stepData.step2 = response.data;
            console.log('📦 STEP 2 - Data guardada:', stepData.step2);
            setTimeout(() => executeStep3(), 1000);
        } else {
            setStepStatus(2, 'error');
            console.error('❌ STEP 2 - Error:', response);
            showError('Error en Paso 2: ' + (response.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        setStepStatus(2, 'error');
        const errorMsg = error.response?.messages?.error || 'Error al obtener información de cuenta';
        showError('Error en Paso 2: ' + errorMsg);
    });
}

function executeStep3() {
    setStepStatus(3, 'active');
    
    const step3Data = {
        debtorCCI: stepData.step2.debtorCCI,
        creditorCCI: transferData.creditorCCI,
        amount: transferData.amount,
        currency: transferData.currency
    };
    
    console.log('🔥 STEP 3 - Datos enviados:', step3Data);
    console.log('📋 STEP 3 - stepData.step2:', stepData.step2);
    console.log('📋 STEP 3 - transferData:', transferData);
    
    makeRequest('<?= base_url('backoffice/transfer/step3') ?>', 'POST', step3Data)
    .then(response => {
        console.log('✅ STEP 3 - Respuesta recibida:', response);
        if (response.success) {
            setStepStatus(3, 'completed');
            stepData.step3 = response.data;
            console.log('📦 STEP 3 - Data guardada:', stepData.step3);
            setTimeout(() => showConfirmation(), 1000);
        } else {
            setStepStatus(3, 'error');
            console.error('❌ STEP 3 - Error:', response);
            showError('Error en Paso 3: ' + (response.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        setStepStatus(3, 'error');
        const errorMsg = error.response?.messages?.error || 'Error al calcular comisión';
        showError('Error en Paso 3: ' + errorMsg);
    });
}

function showConfirmation() {
    // Populate confirmation data
    setText('confirmCreditorName', stepData.step2.creditorName);
    setText('confirmCreditorCCI', transferData.creditorCCI);
    setText('confirmAmount', transferData.amount + ' ' + transferData.currency);
    setText('confirmCurrency', transferData.currency);
    setText('confirmFeeAmount', stepData.step3.feeAmount + ' ' + transferData.currency);
    setText('confirmTotalAmount', stepData.step3.totalAmount + ' ' + transferData.currency);
    setText('confirmFeeCode', stepData.step3.feeCode);
    
    showElement('confirmationArea');
    setTimeout(() => scrollToElement('confirmationArea'), 100);
}

function executeTransfer() {
    setStepStatus(4, 'active');
    hideElement('confirmationArea');
    
    const executeData = {
        debtorCCI: stepData.step2.debtorCCI,
        creditorCCI: transferData.creditorCCI,
        amount: transferData.amount,
        currency: transferData.currency,
        feeAmount: stepData.step3.feeAmount,
        feeCode: stepData.step3.feeCode,
        applicationCriteria: stepData.step3.applicationCriteria,
        messageTypeId: stepData.step2.messageTypeId,
        instructionId: stepData.step2.instructionId,
        unstructuredInformation: transferData.unstructuredInformation,
        feeId: stepData.step3.feeId,
        feeLigo: stepData.step3.feeLigo,
        transfer_type: transferData.transfer_type || 'regular'
    };
    
    console.log('🚀 STEP 4 - PAYLOAD COMPLETO enviado al backend:', JSON.stringify(executeData, null, 2));
    console.log('🔍 STEP 4 - Tipos de datos del payload:');
    Object.keys(executeData).forEach(key => {
        console.log(`   ${key}: ${typeof executeData[key]} (${executeData[key]})`);
    });
    
    makeRequest('<?= base_url('backoffice/transfer/step4') ?>', 'POST', executeData)
    .then(response => {
        // Check if API call succeeded (response.data exists)
        if (response.data) {
            stepData.step4 = response.data;
            
            // Check the actual transfer success status
            if (response.data.success) {
                setStepStatus(4, 'completed');
                displayTransferSuccess(response.data);
            } else {
                // Transfer was processed but failed/pending
                setStepStatus(4, 'error');
                displayTransferResult(response.data);
            }
        } else {
            setStepStatus(4, 'error');
            showError('Error en Paso 4: ' + (response.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        setStepStatus(4, 'error');
        const errorMsg = error.response?.messages?.error || 'Error al ejecutar transferencia';
        showError('Error en Paso 4: ' + errorMsg);
    });
}

function cancelTransfer() {
    hideElement('confirmationArea');
    hideElement('progressSteps');
    resetSteps();
}

function displayTransferSuccess(data) {
    displayTransferResult(data);
}

function displayTransferResult(data) {
    // Update result area with status-specific styling
    const resultDiv = document.getElementById('transferResult');
    const alertDiv = resultDiv.querySelector('.alert');
    
    // Clear existing classes
    alertDiv.classList.remove('alert-success', 'alert-warning', 'alert-danger');
    
    // Determine status styling and messages
    let statusClass = 'alert-success';
    let statusIcon = 'fas fa-check-circle';
    let statusTitle = 'Transferencia Procesada Exitosamente';
    
    if (data.status === 'failed') {
        statusClass = 'alert-danger';
        statusIcon = 'fas fa-exclamation-triangle';
        statusTitle = 'Transferencia Rechazada';
    } else if (data.status === 'pending') {
        statusClass = 'alert-warning';
        statusIcon = 'fas fa-clock';
        statusTitle = 'Transferencia Pendiente';
    }
    
    // Apply appropriate styling
    alertDiv.classList.add(statusClass);
    
    // Update content
    const titleElement = alertDiv.querySelector('h5');
    titleElement.innerHTML = `<i class="${statusIcon}"></i> ${statusTitle}`;
    
    // Add status message if available
    if (data.message && data.message !== 'Transferencia aceptada y completada exitosamente') {
        const existingMessage = alertDiv.querySelector('.status-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'status-message mt-2 p-2 bg-light border-left border-primary';
        messageDiv.innerHTML = `<strong>Detalle:</strong> ${data.message}`;
        
        // Add retry instructions if available
        if (data.retry_required && data.retry_instructions) {
            const retryDiv = document.createElement('div');
            retryDiv.className = 'retry-instructions mt-2 p-2 bg-warning text-dark rounded';
            retryDiv.innerHTML = `<strong>Instrucción de reintento:</strong> ${data.retry_instructions}`;
            messageDiv.appendChild(retryDiv);
        }
        
        titleElement.parentNode.appendChild(messageDiv);
    }
    
    // Update standard fields
    setText('resultTransferId', data.transferId || 'N/A');
    setText('resultAccountInquiryId', stepData.step1?.accountInquiryId || 'N/A');
    setText('resultStatus', data.status || 'Desconocido');
    setText('resultAmount', transferData.amount + ' ' + transferData.currency);
    setText('resultDate', new Date().toLocaleString());
    
    // Add response code if available
    const statusSpan = document.getElementById('resultStatus');
    if (data.responseCode) {
        statusSpan.innerHTML = `${data.status || 'Desconocido'} (Código: ${data.responseCode})`;
    }
    
    // Llenar detalles de pasos de forma más legible
    populateStepDetails(stepData.step1 || {}, stepData.step2 || {}, stepData.step3 || {}, data);
    
    showElement('transferResult');
    setTimeout(() => scrollToElement('transferResult'), 100);
}

function showError(message) {
    setText('errorMessage', message);
    showElement('errorResult');
    setTimeout(() => scrollToElement('errorResult'), 100);
}

function showTransferDetails() {
    const details = document.getElementById('transferDetails');
    if (details) {
        details.style.display = details.style.display === 'none' ? 'block' : 'none';
    }
}

function populateStepDetails(step1, step2, step3, step4) {
    // Paso 1: Consulta de Cuenta - mostrar datos enviados
    const step1Content = formatStepDetails('Paso 1: Consulta de Cuenta', {
        'CCI Acreedor': transferData.creditorCCI,
        'Moneda': transferData.currency,
        'Account Inquiry ID': step1.accountInquiryId || 'N/A',
        'Datos del Deudor': step1.debtorData ? JSON.stringify(step1.debtorData, null, 2) : 'N/A',
        'Datos del Acreedor': step1.creditorData ? JSON.stringify(step1.creditorData, null, 2) : 'N/A',
        'Respuesta Cruda': step1.rawResponse ? JSON.stringify(step1.rawResponse, null, 2) : 'N/A'
    });
    setText('step1Details', step1Content);

    // Paso 2: Respuesta de Consulta - mostrar información obtenida
    const step2Content = formatStepDetails('Paso 2: Respuesta de Consulta', {
        'CCI Deudor Obtenido': step2.debtorCCI || 'N/A',
        'Nombre Acreedor': step2.creditorName || 'N/A', 
        'Message Type ID': step2.messageTypeId || 'N/A',
        'Instruction ID': step2.instructionId || 'N/A',
        'Respuesta Cruda': step2.rawResponse ? JSON.stringify(step2.rawResponse, null, 2) : 'N/A'
    });
    setText('step2Details', step2Content);

    // Paso 3: Cálculo de Comisión - mostrar comisiones
    const step3Content = formatStepDetails('Paso 3: Cálculo de Comisión', {
        'Monto Original': transferData.amount + ' ' + transferData.currency,
        'Comisión Calculada': (step3.feeAmount || 0) + ' ' + transferData.currency,
        'Código de Comisión': step3.feeCode || 'N/A',
        'Criterio de Aplicación': step3.applicationCriteria || 'N/A',
        'Fee ID': step3.feeId || 'N/A',
        'Fee Ligo': step3.feeLigo || 'N/A',
        'Total a Debitar': (step3.totalAmount || 0) + ' ' + transferData.currency,
        'Respuesta Cruda': step3.rawResponse ? JSON.stringify(step3.rawResponse, null, 2) : 'N/A'
    });
    setText('step3Details', step3Content);

    // Paso 4: Ejecución - mostrar resultado final
    const step4Content = formatStepDetails('Paso 4: Ejecución de Transferencia', {
        'Transfer ID': step4.transferId || 'N/A',
        'Estado Final': step4.status || 'N/A',
        'Código de Respuesta': step4.responseCode || 'N/A',
        'Mensaje': step4.message || 'N/A',
        'Trace': step4.trace || 'N/A',
        'Reference Number': step4.retrievalReferenceNumber || 'N/A',
        'Settlement Date': step4.settlementDate || 'N/A',
        'Interbankk Amount': step4.interbankSettlementAmount || 'N/A',
        'Instrucciones de Reintento': step4.retry_instructions || 'No requerido',
        'Respuesta Completa': step4.transferResponse ? JSON.stringify(step4.transferResponse, null, 2) : 'N/A'
    });
    setText('step4Details', step4Content);
}

function formatStepDetails(stepTitle, data) {
    let content = `=== ${stepTitle} ===\n\n`;
    
    for (const [key, value] of Object.entries(data)) {
        if (key === 'Respuesta Cruda' || key === 'Respuesta Completa') {
            content += `${key}:\n${value}\n\n`;
        } else {
            content += `${key}: ${value}\n`;
        }
    }
    
    return content;
}

function clearTransferForm() {
    const form = document.getElementById('transferForm');
    if (form) {
        form.reset();
    }
    
    hideElement('transferResult');
    hideElement('errorResult');
    hideElement('transferDetails');
    hideElement('confirmationArea');
    hideElement('progressSteps');
    resetSteps();
    transferData = {};
    stepData = {};
}

// Function to update organization data when selected from dropdown
function updateOrganizationData() {
    const select = document.getElementById('organization_id');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        const cci = selectedOption.getAttribute('data-cci');
        const name = selectedOption.getAttribute('data-name');
        const code = selectedOption.getAttribute('data-code');
        
        // Update CCI field
        document.getElementById('creditorCCI').value = cci;
        
        // Update organization info text
        document.getElementById('organizationInfo').textContent = `CCI de 20 dígitos de la organización: ${name} (${code})`;
        
        // Update the concept field with organization code
        document.getElementById('unstructuredInformation').value = `Pago de comisiones a ${code}`;
        document.getElementById('unstructuredInformation').placeholder = `Pago de comisiones a ${code}`;
        
        console.log('Organization selected:', { name, code, cci });
    } else {
        // Clear fields if no organization selected
        document.getElementById('creditorCCI').value = '';
        document.getElementById('organizationInfo').textContent = 'Seleccione una organización para ver su CCI';
        document.getElementById('unstructuredInformation').value = '';
        document.getElementById('unstructuredInformation').placeholder = 'Seleccione una organización primero';
    }
}

// Function to refresh balance via AJAX
function refreshBalance() {
    const btn = document.getElementById('refreshBalanceBtn');
    const balanceDisplay = document.getElementById('availableBalance');
    const statusDisplay = document.getElementById('balanceStatus');
    const amountField = document.getElementById('amount');
    const maxAmountText = document.getElementById('maxAmountText');
    
    // Show loading state
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
    btn.disabled = true;
    balanceDisplay.textContent = '--';
    statusDisplay.textContent = 'Consultando balance...';
    
    // Make AJAX request to getBalance endpoint
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '<?= base_url('backoffice/getBalance') ?>', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    // Add CSRF token
    const csrfToken = getCSRFToken();
    const requestData = `${csrfToken.name}=${csrfToken.value}`;
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            // Reset button state
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Actualizar Balance';
            btn.disabled = false;
            
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    console.log('Balance response:', response);
                    
                    if (response.success && response.data && response.data.balance) {
                        const balance = parseFloat(response.data.balance);
                        balanceDisplay.textContent = 'S/. ' + balance.toFixed(2);
                        statusDisplay.textContent = 'Balance general de la cuenta CCI centralizada';
                        
                        // Update amount field max value and helper text
                        if (amountField) {
                            amountField.setAttribute('max', balance);
                        }
                        if (maxAmountText) {
                            maxAmountText.textContent = `Máximo disponible: S/. ${balance.toFixed(2)} (balance CCI centralizada)`;
                        }
                    } else {
                        balanceDisplay.textContent = 'Error';
                        statusDisplay.textContent = response.message || 'No se pudo obtener el balance';
                    }
                } catch (e) {
                    console.error('Error parsing balance response:', e);
                    balanceDisplay.textContent = 'Error';
                    statusDisplay.textContent = 'Error al procesar la respuesta del servidor';
                }
            } else {
                balanceDisplay.textContent = 'Error';
                statusDisplay.textContent = 'Error de conexión al servidor';
            }
        }
    };
    
    xhr.send(requestData);
}
</script>
<?= $this->endSection() ?>