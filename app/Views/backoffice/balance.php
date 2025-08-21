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
                    <h3 class="card-title">Balance de Cuenta Ligo</h3>
                    <div class="card-tools">
                        <a href="<?= base_url('backoffice') ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        Se consultará el balance de la cuenta de la organización seleccionada automáticamente.
                    </div>
                    
                    <form id="balanceForm">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Consultar Balance de la Organización
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <div id="loading" class="text-center" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Cargando...</span>
                        </div>
                        <p class="mt-2">Consultando balance...</p>
                    </div>
                    
                    <div id="balanceResult" style="display: none;">
                        <hr>
                        <h5>Resultado de la Consulta</h5>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">Balance Disponible</h6>
                                        <h2 class="text-success mb-3">
                                            <i class="fas fa-coins"></i> <span id="resultBalance"></span>
                                        </h2>
                                        <p class="card-text">
                                            <strong>Estado de la consulta:</strong> <span id="resultStatus" class="badge badge-success"></span><br>
                                            <strong>Última actualización:</strong> <span id="resultTimestamp"></span>
                                        </p>
                                        
                                        <!-- Botón de Retiro -->
                                        <div class="mt-3">
                                            <button class="btn btn-warning btn-lg" id="withdrawBtn" onclick="showWithdrawModal()" style="display: none;">
                                                <i class="fas fa-money-bill-wave"></i> Retirar a mi CCI
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Información Adicional</h6>
                                        <p class="card-text small">
                                            <strong>CCI:</strong> <span id="resultCCI" class="text-muted"></span><br>
                                            <strong>Banco:</strong> <span id="resultBank" class="text-muted"></span>
                                        </p>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle"></i> 
                                            La API de Ligo solo proporciona el balance actual.
                                        </small>
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

<!-- Modal de Retiro -->
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-labelledby="withdrawModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="withdrawModalLabel">
                    <i class="fas fa-money-bill-wave"></i> Retirar Fondos a mi CCI
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Retiro de fondos:</strong> Los fondos serán transferidos directamente a la cuenta CCI de tu organización.
                </div>
                
                <form id="withdrawForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="withdrawAmount">Monto a Retirar *</label>
                                <input type="number" class="form-control" id="withdrawAmount" 
                                       step="0.01" min="0.01" required>
                                <small class="form-text text-muted">
                                    Disponible: <span id="availableForWithdraw"></span>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="withdrawCurrency">Moneda *</label>
                                <select class="form-control" id="withdrawCurrency" required>
                                    <option value="PEN">PEN - Soles</option>
                                    <option value="USD">USD - Dólares</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="withdrawConcept">Concepto del Retiro</label>
                        <textarea class="form-control" id="withdrawConcept" rows="2" 
                                  placeholder="Retiro de fondos disponibles">Retiro de fondos disponibles</textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Información del Destino:</strong><br>
                        CCI: <span id="organizationCCI"><?= esc($organization['cci'] ?? 'No disponible') ?></span><br>
                        Organización: <span id="organizationName"><?= esc($organization['name'] ?? 'No disponible') ?></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" onclick="processWithdrawal()">
                    <i class="fas fa-check"></i> Procesar Retiro
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const balanceForm = document.getElementById('balanceForm');
    
    if (balanceForm) {
        balanceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const loading = document.getElementById('loading');
            const balanceResult = document.getElementById('balanceResult');
            const errorResult = document.getElementById('errorResult');
            const errorMessage = document.getElementById('errorMessage');
            
            if (loading) loading.style.display = 'block';
            if (balanceResult) balanceResult.style.display = 'none';
            if (errorResult) errorResult.style.display = 'none';
            
            // Obtener token CSRF usando los nombres correctos de CodeIgniter 4
            const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfNameMeta = document.querySelector('meta[name="csrf-name"]');
            
            if (!csrfTokenMeta || !csrfNameMeta) {
                console.error('CSRF meta tags not found');
                console.log('Available meta tags:', Array.from(document.querySelectorAll('meta')).map(m => m.name));
                if (errorMessage) errorMessage.textContent = 'Error: Token CSRF no encontrado';
                if (errorResult) errorResult.style.display = 'block';
                if (loading) loading.style.display = 'none';
                return;
            }
            
            const csrfToken = csrfTokenMeta.getAttribute('content');
            const csrfName = csrfNameMeta.getAttribute('content');
            
            console.log('CSRF Token:', csrfToken);
            console.log('CSRF Name:', csrfName);
            console.log('Making POST request to:', '<?= base_url('backoffice/balance') ?>');
            
            fetch('<?= base_url('backoffice/balance') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    [csrfName]: csrfToken
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(errorData => {
                        throw new Error(errorData.messages?.error || errorData.message || 'Error del servidor');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (loading) loading.style.display = 'none';
                
                if (data.data) {
                    const resultCCI = document.getElementById('resultCCI');
                    const resultBank = document.getElementById('resultBank');
                    const resultStatus = document.getElementById('resultStatus');
                    const resultBalance = document.getElementById('resultBalance');
                    const resultTimestamp = document.getElementById('resultTimestamp');
                    
                    if (resultCCI) resultCCI.textContent = data.data.debtorCCI || 'No disponible en esta respuesta';
                    if (resultBank) resultBank.textContent = data.data.bankName || 'No disponible en esta respuesta';
                    if (resultStatus) resultStatus.textContent = data.status === 1 ? 'Activo' : 'Inactivo';
                    if (resultBalance) resultBalance.textContent = (data.data.amount || '0.00') + ' ' + (data.data.currency_symbol || 'PEN');
                    if (resultTimestamp) resultTimestamp.textContent = data.date || new Date().toLocaleString();
                    
                    // Mostrar botón de retiro si hay balance disponible
                    const balanceAmount = parseFloat(data.data.amount || 0);
                    const withdrawBtn = document.getElementById('withdrawBtn');
                    if (withdrawBtn && balanceAmount > 0) {
                        withdrawBtn.style.display = 'block';
                        // Guardar el balance para usar en el modal
                        withdrawBtn.setAttribute('data-balance', balanceAmount);
                        withdrawBtn.setAttribute('data-currency', data.data.currency_symbol || 'PEN');
                    }
                    
                    if (balanceResult) balanceResult.style.display = 'block';
                } else {
                    const errorMessage = document.getElementById('errorMessage');
                    if (errorMessage) errorMessage.textContent = 'No se pudo obtener la información del balance';
                    if (errorResult) errorResult.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Fetch error details:', error);
                console.error('Error type:', typeof error);
                console.error('Error message:', error.message);
                
                if (loading) loading.style.display = 'none';
                const errorMessage = document.getElementById('errorMessage');
                
                // Intentar obtener el mensaje de error detallado
                if (error instanceof Response) {
                    console.log('Error is Response object, status:', error.status);
                    error.json().then(errorData => {
                        console.log('Error response data:', errorData);
                        const message = errorData.messages?.error || errorData.message || 'Error al consultar el balance';
                        if (errorMessage) errorMessage.textContent = message;
                    }).catch(() => {
                        console.log('Could not parse error response as JSON');
                        if (errorMessage) errorMessage.textContent = 'Error al consultar el balance';
                    });
                } else {
                    console.log('Error is not Response object');
                    if (errorMessage) errorMessage.textContent = 'Error al consultar el balance: ' + error.message;
                }
                
                if (errorResult) errorResult.style.display = 'block';
                console.error('Error:', error);
            });
        });
    }
});

// Funciones para el modal de retiro
function showWithdrawModal() {
    const withdrawBtn = document.getElementById('withdrawBtn');
    const balance = parseFloat(withdrawBtn.getAttribute('data-balance') || 0);
    const currency = withdrawBtn.getAttribute('data-currency') || 'PEN';
    
    // Configurar el modal con la información actual
    const withdrawAmount = document.getElementById('withdrawAmount');
    const availableForWithdraw = document.getElementById('availableForWithdraw');
    const withdrawCurrency = document.getElementById('withdrawCurrency');
    
    if (withdrawAmount) {
        withdrawAmount.max = balance;
        withdrawAmount.value = '';
    }
    
    if (availableForWithdraw) {
        availableForWithdraw.textContent = balance.toFixed(2) + ' ' + currency;
    }
    
    if (withdrawCurrency) {
        withdrawCurrency.value = currency;
    }
    
    // Mostrar el modal
    const modal = new bootstrap.Modal(document.getElementById('withdrawModal'));
    modal.show();
}

function processWithdrawal() {
    const form = document.getElementById('withdrawForm');
    const amount = document.getElementById('withdrawAmount').value;
    const currency = document.getElementById('withdrawCurrency').value;
    const concept = document.getElementById('withdrawConcept').value;
    
    // Validaciones
    if (!amount || parseFloat(amount) <= 0) {
        alert('Por favor ingresa un monto válido');
        return;
    }
    
    const withdrawBtn = document.getElementById('withdrawBtn');
    const maxBalance = parseFloat(withdrawBtn.getAttribute('data-balance') || 0);
    
    if (parseFloat(amount) > maxBalance) {
        alert(`El monto no puede exceder el balance disponible: ${maxBalance.toFixed(2)}`);
        return;
    }
    
    // Confirmar la transferencia
    if (!confirm(`¿Confirmas el retiro de ${amount} ${currency} a tu cuenta CCI?`)) {
        return;
    }
    
    // Preparar datos para la transferencia
    const withdrawData = {
        amount: amount,
        currency: currency,
        unstructuredInformation: concept || 'Retiro de fondos disponibles',
        type: 'withdrawal'
    };
    
    // Redireccionar a la página de transferencias con los datos
    const params = new URLSearchParams(withdrawData);
    window.location.href = '<?= base_url('backoffice/transfer') ?>?' + params.toString();
}
</script>
<?= $this->endSection() ?>