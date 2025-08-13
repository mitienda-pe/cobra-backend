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
                    <form id="balanceForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="debtorCCI">CCI del Deudor *</label>
                                    <input type="text" class="form-control" id="debtorCCI" name="debtorCCI" 
                                           placeholder="Ingrese el CCI de 20 dígitos" maxlength="20" required>
                                    <small class="form-text text-muted">
                                        Ejemplo: 92100171571742601040
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Consultar Balance
                                        </button>
                                    </div>
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
$(document).ready(function() {
    $('#balanceForm').on('submit', function(e) {
        e.preventDefault();
        
        const debtorCCI = $('#debtorCCI').val().trim();
        
        if (!debtorCCI) {
            alert('Por favor ingrese el CCI del deudor');
            return;
        }
        
        if (debtorCCI.length !== 20) {
            alert('El CCI debe tener exactamente 20 dígitos');
            return;
        }
        
        $('#loading').show();
        $('#balanceResult').hide();
        $('#errorResult').hide();
        
        $.ajax({
            url: '<?= base_url('backoffice/balance') ?>',
            type: 'POST',
            data: {
                debtorCCI: debtorCCI
            },
            success: function(response) {
                $('#loading').hide();
                
                if (response.data) {
                    $('#resultCCI').text(debtorCCI);
                    $('#resultBank').text(response.data.bankName || 'No disponible');
                    $('#resultStatus').text(response.data.status || 'Activo');
                    $('#resultBalance').text((response.data.balance || '0.00') + ' ' + (response.data.currency || 'PEN'));
                    $('#resultTimestamp').text(new Date().toLocaleString());
                    $('#balanceResult').show();
                } else {
                    $('#errorMessage').text('No se pudo obtener la información del balance');
                    $('#errorResult').show();
                }
            },
            error: function(xhr) {
                $('#loading').hide();
                const response = xhr.responseJSON;
                $('#errorMessage').text(response?.messages?.error || 'Error al consultar el balance');
                $('#errorResult').show();
            }
        });
    });
    
    // Validar solo números en el campo CCI
    $('#debtorCCI').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
});
</script>
<?= $this->endSection() ?>