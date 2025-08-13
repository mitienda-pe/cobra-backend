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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const balanceForm = document.getElementById('balanceForm');
    
    if (balanceForm) {
        balanceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const loading = document.getElementById('loading');
            const balanceResult = document.getElementById('balanceResult');
            const errorResult = document.getElementById('errorResult');
            
            if (loading) loading.style.display = 'block';
            if (balanceResult) balanceResult.style.display = 'none';
            if (errorResult) errorResult.style.display = 'none';
            
            // Obtener token CSRF
            const csrfToken = document.querySelector('meta[name="X-CSRF-TOKEN"]').getAttribute('content');
            
            fetch('<?= base_url('backoffice/balance') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: new URLSearchParams({
                    'csrf_token_name': csrfToken
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
                    
                    if (balanceResult) balanceResult.style.display = 'block';
                } else {
                    const errorMessage = document.getElementById('errorMessage');
                    if (errorMessage) errorMessage.textContent = 'No se pudo obtener la información del balance';
                    if (errorResult) errorResult.style.display = 'block';
                }
            })
            .catch(error => {
                if (loading) loading.style.display = 'none';
                const errorMessage = document.getElementById('errorMessage');
                
                // Intentar obtener el mensaje de error detallado
                if (error instanceof Response) {
                    error.json().then(errorData => {
                        const message = errorData.messages?.error || errorData.message || 'Error al consultar el balance';
                        if (errorMessage) errorMessage.textContent = message;
                    }).catch(() => {
                        if (errorMessage) errorMessage.textContent = 'Error al consultar el balance';
                    });
                } else {
                    if (errorMessage) errorMessage.textContent = 'Error al consultar el balance: ' + error.message;
                }
                
                if (errorResult) errorResult.style.display = 'block';
                console.error('Error:', error);
            });
        });
    }
});
</script>
<?= $this->endSection() ?>