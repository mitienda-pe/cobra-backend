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
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">Información de la Cuenta</h6>
                                        <p class="card-text">
                                            <strong>CCI:</strong> <span id="resultCCI"></span><br>
                                            <strong>Banco:</strong> <span id="resultBank"></span><br>
                                            <strong>Estado:</strong> <span id="resultStatus"></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">Balance Disponible</h6>
                                        <h3 class="text-success">
                                            <span id="resultBalance"></span>
                                        </h3>
                                        <small class="text-muted">
                                            Última actualización: <span id="resultTimestamp"></span>
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
            .then(response => response.json())
            .then(data => {
                if (loading) loading.style.display = 'none';
                
                if (data.data) {
                    const resultCCI = document.getElementById('resultCCI');
                    const resultBank = document.getElementById('resultBank');
                    const resultStatus = document.getElementById('resultStatus');
                    const resultBalance = document.getElementById('resultBalance');
                    const resultTimestamp = document.getElementById('resultTimestamp');
                    
                    if (resultCCI) resultCCI.textContent = data.data.debtorCCI || 'No disponible';
                    if (resultBank) resultBank.textContent = data.data.bankName || 'No disponible';
                    if (resultStatus) resultStatus.textContent = data.data.status || 'Activo';
                    if (resultBalance) resultBalance.textContent = (data.data.balance || '0.00') + ' ' + (data.data.currency || 'PEN');
                    if (resultTimestamp) resultTimestamp.textContent = new Date().toLocaleString();
                    
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
                if (errorMessage) errorMessage.textContent = 'Error al consultar el balance';
                if (errorResult) errorResult.style.display = 'block';
                console.error('Error:', error);
            });
        });
    }
});
</script>
<?= $this->endSection() ?>