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
                    <h3 class="card-title">Transacciones Ligo</h3>
                    <div class="card-tools">
                        <a href="<?= base_url('backoffice') ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form id="transactionsForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="startDate">Fecha Inicio *</label>
                                    <input type="date" class="form-control" id="startDate" name="startDate" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="endDate">Fecha Fin *</label>
                                    <input type="date" class="form-control" id="endDate" name="endDate" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="debtorCCI">CCI Deudor (Opcional)</label>
                                    <input type="text" class="form-control" id="debtorCCI" name="debtorCCI" 
                                           placeholder="20 dígitos" maxlength="20">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="creditorCCI">CCI Acreedor (Opcional)</label>
                                    <input type="text" class="form-control" id="creditorCCI" name="creditorCCI" 
                                           placeholder="20 dígitos" maxlength="20">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="page">Página</label>
                                    <input type="number" class="form-control" id="page" name="page" value="1" min="1">
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Buscar Transacciones
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="clearForm()">
                                            <i class="fas fa-eraser"></i> Limpiar
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
                        <p class="mt-2">Buscando transacciones...</p>
                    </div>
                    
                    <div id="transactionsResult" style="display: none;">
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5>Resultados de la Búsqueda</h5>
                            <div>
                                <span class="badge badge-info" id="totalResults">0 resultados</span>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha</th>
                                        <th>CCI Deudor</th>
                                        <th>CCI Acreedor</th>
                                        <th>Monto</th>
                                        <th>Moneda</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="transactionsTableBody">
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <button class="btn btn-outline-primary" id="prevPage" disabled>
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </button>
                                <button class="btn btn-outline-primary" id="nextPage">
                                    Siguiente <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                            <span id="pageInfo">Página 1</span>
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
let currentPage = 1;
let currentFilters = {};

$(document).ready(function() {
    // Establecer fecha por defecto (último mes)
    const today = new Date();
    const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());
    
    $('#endDate').val(today.toISOString().split('T')[0]);
    $('#startDate').val(lastMonth.toISOString().split('T')[0]);
    
    $('#transactionsForm').on('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        searchTransactions();
    });
    
    $('#prevPage').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            searchTransactions();
        }
    });
    
    $('#nextPage').on('click', function() {
        currentPage++;
        searchTransactions();
    });
    
    // Validar solo números en campos CCI
    $('#debtorCCI, #creditorCCI').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
});

function searchTransactions() {
    const formData = {
        startDate: $('#startDate').val(),
        endDate: $('#endDate').val(),
        debtorCCI: $('#debtorCCI').val().trim(),
        creditorCCI: $('#creditorCCI').val().trim(),
        page: currentPage
    };
    
    if (!formData.startDate || !formData.endDate) {
        alert('Por favor seleccione las fechas de inicio y fin');
        return;
    }
    
    currentFilters = formData;
    
    $('#loading').show();
    $('#transactionsResult').hide();
    $('#errorResult').hide();
    
    $.ajax({
        url: '<?= base_url('backoffice/transactions') ?>',
        type: 'POST',
        data: formData,
        success: function(response) {
            $('#loading').hide();
            
            if (response.data && response.data.transactions) {
                displayTransactions(response.data);
                $('#transactionsResult').show();
            } else {
                $('#errorMessage').text('No se encontraron transacciones');
                $('#errorResult').show();
            }
        },
        error: function(xhr) {
            $('#loading').hide();
            const response = xhr.responseJSON;
            $('#errorMessage').text(response?.messages?.error || 'Error al buscar transacciones');
            $('#errorResult').show();
        }
    });
}

function displayTransactions(data) {
    const transactions = data.transactions || [];
    const total = data.total || 0;
    const tbody = $('#transactionsTableBody');
    
    tbody.empty();
    $('#totalResults').text(total + ' resultados');
    
    if (transactions.length === 0) {
        tbody.append('<tr><td colspan="8" class="text-center">No se encontraron transacciones</td></tr>');
        return;
    }
    
    transactions.forEach(function(transaction) {
        const row = `
            <tr>
                <td>${transaction.id || 'N/A'}</td>
                <td>${formatDate(transaction.date)}</td>
                <td>${transaction.debtorCCI || 'N/A'}</td>
                <td>${transaction.creditorCCI || 'N/A'}</td>
                <td class="text-right">${formatAmount(transaction.amount)}</td>
                <td>${transaction.currency || 'PEN'}</td>
                <td><span class="badge badge-${getStatusBadge(transaction.status)}">${transaction.status || 'Pendiente'}</span></td>
                <td>
                    <a href="<?= base_url('backoffice/transaction-detail') ?>/${transaction.id}" 
                       class="btn btn-sm btn-info" title="Ver Detalle">
                        <i class="fas fa-eye"></i>
                    </a>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
    
    // Actualizar controles de paginación
    $('#prevPage').prop('disabled', currentPage <= 1);
    $('#nextPage').prop('disabled', transactions.length < 10); // Asumiendo 10 por página
    $('#pageInfo').text('Página ' + currentPage);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-PE');
}

function formatAmount(amount) {
    if (!amount) return '0.00';
    return parseFloat(amount).toFixed(2);
}

function getStatusBadge(status) {
    switch (status) {
        case 'completed':
        case 'success':
            return 'success';
        case 'failed':
        case 'error':
            return 'danger';
        case 'pending':
            return 'warning';
        default:
            return 'secondary';
    }
}

function clearForm() {
    $('#transactionsForm')[0].reset();
    $('#transactionsResult').hide();
    $('#errorResult').hide();
    currentPage = 1;
    
    // Reestablecer fechas por defecto
    const today = new Date();
    const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());
    
    $('#endDate').val(today.toISOString().split('T')[0]);
    $('#startDate').val(lastMonth.toISOString().split('T')[0]);
}
</script>
<?= $this->endSection() ?>