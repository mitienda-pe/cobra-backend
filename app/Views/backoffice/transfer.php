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
                        <p class="mb-0">La transferencia ordinaria se ejecuta en 5 pasos automáticos: consulta de cuenta, obtención de respuesta, código de comisión, ejecución y confirmación.</p>
                    </div>
                    
                    <form id="transferForm">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Datos del Deudor</h5>
                                <div class="form-group">
                                    <label for="debtorParticipantCode">Código Participante Deudor *</label>
                                    <input type="text" class="form-control" id="debtorParticipantCode" name="debtorParticipantCode" required>
                                </div>
                                <div class="form-group">
                                    <label for="debtorName">Nombre del Deudor *</label>
                                    <input type="text" class="form-control" id="debtorName" name="debtorName" required>
                                </div>
                                <div class="form-group">
                                    <label for="debtorId">Documento del Deudor *</label>
                                    <input type="text" class="form-control" id="debtorId" name="debtorId" required>
                                </div>
                                <div class="form-group">
                                    <label for="debtorIdCode">Tipo de Documento *</label>
                                    <select class="form-control" id="debtorIdCode" name="debtorIdCode" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="1">DNI</option>
                                        <option value="6">RUC</option>
                                        <option value="7">Pasaporte</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="debtorAddressLine">Dirección del Deudor *</label>
                                    <input type="text" class="form-control" id="debtorAddressLine" name="debtorAddressLine" required>
                                </div>
                                <div class="form-group">
                                    <label for="debtorMobileNumber">Teléfono del Deudor *</label>
                                    <input type="text" class="form-control" id="debtorMobileNumber" name="debtorMobileNumber" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5>Datos del Acreedor y Transferencia</h5>
                                <div class="form-group">
                                    <label for="creditorParticipantCode">Código Participante Acreedor *</label>
                                    <input type="text" class="form-control" id="creditorParticipantCode" name="creditorParticipantCode" required>
                                </div>
                                <div class="form-group">
                                    <label for="creditorCCI">CCI del Acreedor *</label>
                                    <input type="text" class="form-control" id="creditorCCI" name="creditorCCI" 
                                           placeholder="20 dígitos" maxlength="20" required>
                                </div>
                                <div class="form-group">
                                    <label for="amount">Monto *</label>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           step="0.01" min="0.01" required>
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
                                    <textarea class="form-control" id="unstructuredInformation" name="unstructuredInformation" 
                                              rows="3" placeholder="Descripción opcional de la transferencia"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        <div class="text-center">
                            <button type="submit" class="btn btn-warning btn-lg">
                                <i class="fas fa-exchange-alt"></i> Procesar Transferencia
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg ml-2" onclick="clearTransferForm()">
                                <i class="fas fa-eraser"></i> Limpiar Formulario
                            </button>
                        </div>
                    </form>
                    
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
                                    <h6>Detalles del Proceso (5 Pasos)</h6>
                                </div>
                                <div class="card-body">
                                    <div class="accordion" id="stepsAccordion">
                                        <div class="card">
                                            <div class="card-header" id="step1Header">
                                                <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#step1">
                                                    <i class="fas fa-search text-primary"></i> Paso 1: Consulta de Cuenta
                                                </button>
                                            </div>
                                            <div id="step1" class="collapse" data-parent="#stepsAccordion">
                                                <div class="card-body">
                                                    <pre id="step1Details"></pre>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <div class="card-header" id="step2Header">
                                                <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#step2">
                                                    <i class="fas fa-reply text-info"></i> Paso 2: Respuesta de Consulta
                                                </button>
                                            </div>
                                            <div id="step2" class="collapse" data-parent="#stepsAccordion">
                                                <div class="card-body">
                                                    <pre id="step2Details"></pre>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <div class="card-header" id="step3Header">
                                                <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#step3">
                                                    <i class="fas fa-percentage text-warning"></i> Paso 3: Código de Comisión
                                                </button>
                                            </div>
                                            <div id="step3" class="collapse" data-parent="#stepsAccordion">
                                                <div class="card-body">
                                                    <pre id="step3Details"></pre>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <div class="card-header" id="step4Header">
                                                <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#step4">
                                                    <i class="fas fa-paper-plane text-success"></i> Paso 4: Ejecución de Transferencia
                                                </button>
                                            </div>
                                            <div id="step4" class="collapse" data-parent="#stepsAccordion">
                                                <div class="card-body">
                                                    <pre id="step4Details"></pre>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card">
                                            <div class="card-header" id="step5Header">
                                                <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#step5">
                                                    <i class="fas fa-check text-success"></i> Paso 5: Confirmación
                                                </button>
                                            </div>
                                            <div id="step5" class="collapse" data-parent="#stepsAccordion">
                                                <div class="card-body">
                                                    <pre id="step5Details"></pre>
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

<script>
let transferData = {};

$(document).ready(function() {
    $('#transferForm').on('submit', function(e) {
        e.preventDefault();
        processTransfer();
    });
    
    // Validar solo números en CCI
    $('#creditorCCI').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    
    // Validar solo números en teléfono
    $('#debtorMobileNumber').on('input', function() {
        this.value = this.value.replace(/[^0-9+]/g, '');
    });
});

function processTransfer() {
    const formData = {
        debtorParticipantCode: $('#debtorParticipantCode').val(),
        creditorParticipantCode: $('#creditorParticipantCode').val(),
        debtorName: $('#debtorName').val(),
        debtorId: $('#debtorId').val(),
        debtorIdCode: $('#debtorIdCode').val(),
        debtorAddressLine: $('#debtorAddressLine').val(),
        debtorMobileNumber: $('#debtorMobileNumber').val(),
        creditorCCI: $('#creditorCCI').val(),
        amount: $('#amount').val(),
        currency: $('#currency').val(),
        unstructuredInformation: $('#unstructuredInformation').val()
    };
    
    // Validaciones básicas
    if (!formData.creditorCCI || formData.creditorCCI.length !== 20) {
        alert('El CCI del acreedor debe tener exactamente 20 dígitos');
        return;
    }
    
    if (!formData.amount || parseFloat(formData.amount) <= 0) {
        alert('El monto debe ser mayor a 0');
        return;
    }
    
    $('#loading').show();
    $('#transferResult').hide();
    $('#errorResult').hide();
    
    // Simular progreso
    simulateProgress();
    
    $.ajax({
        url: '<?= base_url('backoffice/transfer') ?>',
        type: 'POST',
        data: formData,
        success: function(response) {
            $('#loading').hide();
            
            if (response.success) {
                transferData = response;
                displayTransferSuccess(response);
                $('#transferResult').show();
            } else {
                $('#errorMessage').text(response.error || 'Error desconocido');
                $('#errorResult').show();
            }
        },
        error: function(xhr) {
            $('#loading').hide();
            const response = xhr.responseJSON;
            $('#errorMessage').text(response?.messages?.error || 'Error al procesar la transferencia');
            $('#errorResult').show();
        }
    });
}

function simulateProgress() {
    const steps = [
        'Paso 1: Consulta de cuenta...',
        'Paso 2: Obteniendo respuesta...',
        'Paso 3: Calculando comisión...',
        'Paso 4: Ejecutando transferencia...',
        'Paso 5: Confirmando operación...'
    ];
    
    let currentStep = 0;
    const interval = setInterval(function() {
        if (currentStep < steps.length) {
            const progress = ((currentStep + 1) / steps.length) * 100;
            $('#progressBar').css('width', progress + '%');
            $('#progressText').text(steps[currentStep]);
            currentStep++;
        } else {
            clearInterval(interval);
        }
    }, 2000);
}

function displayTransferSuccess(data) {
    $('#resultTransferId').text(data.transfer_id || 'N/A');
    $('#resultAccountInquiryId').text(data.account_inquiry_id || 'N/A');
    $('#resultStatus').text(data.status || 'Completado');
    $('#resultAmount').text($('#amount').val() + ' ' + $('#currency').val());
    $('#resultDate').text(new Date().toLocaleString());
    
    // Llenar detalles de pasos si están disponibles
    if (data.steps) {
        $('#step1Details').text(JSON.stringify(data.steps.account_inquiry, null, 2));
        $('#step2Details').text(JSON.stringify(data.steps.account_inquiry_response, null, 2));
        $('#step3Details').text(JSON.stringify(data.steps.fee_code, null, 2));
        $('#step4Details').text(JSON.stringify(data.steps.transfer_order, null, 2));
        $('#step5Details').text(JSON.stringify(data.steps.transfer_response, null, 2));
    }
}

function showTransferDetails() {
    $('#transferDetails').toggle();
}

function clearTransferForm() {
    $('#transferForm')[0].reset();
    $('#transferResult').hide();
    $('#errorResult').hide();
    $('#transferDetails').hide();
}
</script>
<?= $this->endSection() ?>