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
                    <h3 class="card-title">Detalle de Transacción #<?= $transaction_id ?></h3>
                    <div class="card-tools">
                        <a href="<?= base_url('backoffice/transactions') ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver a Transacciones
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div id="loading" class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando detalles de la transacción...</p>
                    </div>
                    
                    <div id="transactionDetail" style="display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> Información General</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>ID de Transacción:</strong></td>
                                                <td id="detailId"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Fecha:</strong></td>
                                                <td id="detailDate"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Estado:</strong></td>
                                                <td><span id="detailStatus" class="badge"></span></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Tipo:</strong></td>
                                                <td id="detailType"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Canal:</strong></td>
                                                <td id="detailChannel"></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0"><i class="fas fa-dollar-sign"></i> Información Financiera</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Monto:</strong></td>
                                                <td id="detailAmount" class="font-weight-bold text-success"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Moneda:</strong></td>
                                                <td id="detailCurrency"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Comisión:</strong></td>
                                                <td id="detailFee"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Código de Comisión:</strong></td>
                                                <td id="detailFeeCode"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Total:</strong></td>
                                                <td id="detailTotal" class="font-weight-bold"></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0"><i class="fas fa-user"></i> Datos del Deudor</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Nombre:</strong></td>
                                                <td id="debtorName"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Documento:</strong></td>
                                                <td><span id="debtorId"></span> (<span id="debtorIdType"></span>)</td>
                                            </tr>
                                            <tr>
                                                <td><strong>CCI:</strong></td>
                                                <td id="debtorCCI" class="font-family-monospace"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Banco:</strong></td>
                                                <td id="debtorBank"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Teléfono:</strong></td>
                                                <td id="debtorPhone"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Dirección:</strong></td>
                                                <td id="debtorAddress"></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-warning text-dark">
                                        <h6 class="mb-0"><i class="fas fa-user-check"></i> Datos del Acreedor</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Nombre:</strong></td>
                                                <td id="creditorName"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Documento:</strong></td>
                                                <td><span id="creditorId"></span> (<span id="creditorIdType"></span>)</td>
                                            </tr>
                                            <tr>
                                                <td><strong>CCI:</strong></td>
                                                <td id="creditorCCI" class="font-family-monospace"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Banco:</strong></td>
                                                <td id="creditorBank"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Teléfono:</strong></td>
                                                <td id="creditorPhone"></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Dirección:</strong></td>
                                                <td id="creditorAddress"></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-secondary text-white">
                                        <h6 class="mb-0"><i class="fas fa-file-alt"></i> Información Adicional</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Referencia de Transacción:</strong> <span id="detailReference"></span></p>
                                                <p><strong>ID de Instrucción:</strong> <span id="detailInstructionId"></span></p>
                                                <p><strong>Código de Propósito:</strong> <span id="detailPurposeCode"></span></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Mismo Cliente:</strong> <span id="detailSameCustomer"></span></p>
                                                <p><strong>Criterio de Aplicación:</strong> <span id="detailApplicationCriteria"></span></p>
                                                <p><strong>Código de Mensaje:</strong> <span id="detailMessageTypeId"></span></p>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-12">
                                                <p><strong>Información no estructurada:</strong></p>
                                                <div class="bg-light p-2 rounded">
                                                    <span id="detailUnstructuredInfo"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-dark text-white">
                                        <h6 class="mb-0"><i class="fas fa-code"></i> Datos Técnicos</h6>
                                    </div>
                                    <div class="card-body">
                                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleRawData()">
                                            <i class="fas fa-eye"></i> Mostrar/Ocultar Datos Raw
                                        </button>
                                        <div id="rawData" style="display: none;" class="mt-3">
                                            <pre id="rawDataContent" class="bg-light p-3" style="max-height: 300px; overflow-y: auto;"></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="errorResult" class="alert alert-danger" style="display: none;">
                        <h6><i class="fas fa-exclamation-triangle"></i> Error</h6>
                        <p id="errorMessage"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    loadTransactionDetail();
});

function loadTransactionDetail() {
    $.ajax({
        url: '<?= base_url('backoffice/transaction-detail/' . $transaction_id) ?>',
        type: 'GET',
        success: function(response) {
            $('#loading').hide();
            
            if (response.data) {
                displayTransactionDetail(response.data);
                $('#transactionDetail').show();
            } else {
                $('#errorMessage').text('No se pudo cargar el detalle de la transacción');
                $('#errorResult').show();
            }
        },
        error: function(xhr) {
            $('#loading').hide();
            const response = xhr.responseJSON;
            $('#errorMessage').text(response?.messages?.error || 'Error al cargar el detalle');
            $('#errorResult').show();
        }
    });
}

function displayTransactionDetail(data) {
    // Información general
    $('#detailId').text(data.id || '<?= $transaction_id ?>');
    $('#detailDate').text(formatDate(data.date || data.createdAt));
    $('#detailStatus').text(data.status || 'N/A').attr('class', 'badge badge-' + getStatusBadge(data.status));
    $('#detailType').text(data.transactionType || data.type || 'N/A');
    $('#detailChannel').text(data.channel || 'N/A');
    
    // Información financiera
    $('#detailAmount').text(formatAmount(data.amount) + ' ' + (data.currency || 'PEN'));
    $('#detailCurrency').text(data.currency || 'PEN');
    $('#detailFee').text(formatAmount(data.feeAmount) + ' ' + (data.currency || 'PEN'));
    $('#detailFeeCode').text(data.feeCode || 'N/A');
    
    const total = parseFloat(data.amount || 0) + parseFloat(data.feeAmount || 0);
    $('#detailTotal').text(formatAmount(total) + ' ' + (data.currency || 'PEN'));
    
    // Datos del deudor
    $('#debtorName').text(data.debtorName || 'N/A');
    $('#debtorId').text(data.debtorId || 'N/A');
    $('#debtorIdType').text(getDocumentType(data.debtorIdCode));
    $('#debtorCCI').text(data.debtorCCI || 'N/A');
    $('#debtorBank').text(data.debtorBank || getBankFromCCI(data.debtorCCI));
    $('#debtorPhone').text(data.debtorMobileNumber || data.debtorPhoneNumber || 'N/A');
    $('#debtorAddress').text(data.debtorAddressLine || 'N/A');
    
    // Datos del acreedor
    $('#creditorName').text(data.creditorName || 'N/A');
    $('#creditorId').text(data.creditorId || 'N/A');
    $('#creditorIdType').text(getDocumentType(data.creditorIdCode));
    $('#creditorCCI').text(data.creditorCCI || 'N/A');
    $('#creditorBank').text(data.creditorBank || getBankFromCCI(data.creditorCCI));
    $('#creditorPhone').text(data.creditorMobileNumber || data.creditorPhoneNumber || 'N/A');
    $('#creditorAddress').text(data.creditorAddressLine || 'N/A');
    
    // Información adicional
    $('#detailReference').text(data.referenceTransactionId || 'N/A');
    $('#detailInstructionId').text(data.instructionId || 'N/A');
    $('#detailPurposeCode').text(data.purposeCode || 'N/A');
    $('#detailSameCustomer').text(data.sameCustomerFlag || 'N/A');
    $('#detailApplicationCriteria').text(data.applicationCriteria || 'N/A');
    $('#detailMessageTypeId').text(data.messageTypeId || 'N/A');
    $('#detailUnstructuredInfo').text(data.unstructuredInformation || 'Sin información adicional');
    
    // Datos raw
    $('#rawDataContent').text(JSON.stringify(data, null, 2));
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-PE') + ' ' + date.toLocaleTimeString('es-PE');
}

function formatAmount(amount) {
    if (!amount) return '0.00';
    return parseFloat(amount).toFixed(2);
}

function getStatusBadge(status) {
    switch (status) {
        case 'completed':
        case 'success':
        case 'exitoso':
            return 'success';
        case 'failed':
        case 'error':
        case 'fallido':
            return 'danger';
        case 'pending':
        case 'pendiente':
            return 'warning';
        default:
            return 'secondary';
    }
}

function getDocumentType(code) {
    switch (code) {
        case '1':
            return 'DNI';
        case '6':
            return 'RUC';
        case '7':
            return 'Pasaporte';
        default:
            return 'N/A';
    }
}

function getBankFromCCI(cci) {
    if (!cci || cci.length < 4) return 'N/A';
    
    const bankCode = cci.substring(0, 4);
    const banks = {
        '0021': 'Banco de Crédito del Perú',
        '0049': 'Banco Pichincha',
        '0080': 'Banco de Comercio',
        '0083': 'Banco Financiero',
        '0091': 'Banco Santander',
        '0106': 'Banco Interbank',
        '0109': 'Banco Ripley'
    };
    
    return banks[bankCode] || 'Banco desconocido';
}

function toggleRawData() {
    $('#rawData').toggle();
}
</script>
<?= $this->endSection() ?>