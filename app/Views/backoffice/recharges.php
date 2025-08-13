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
                    <h3 class="card-title">Recargas Ligo</h3>
                    <div class="card-tools">
                        <a href="<?= base_url('backoffice') ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form id="rechargesForm">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="startDate">Fecha Inicio *</label>
                                    <input type="date" class="form-control" id="startDate" name="startDate" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="endDate">Fecha Fin *</label>
                                    <input type="date" class="form-control" id="endDate" name="endDate" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="page">Página</label>
                                    <input type="number" class="form-control" id="page" name="page" value="1" min="1">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-info btn-block">
                                            <i class="fas fa-search"></i> Buscar
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-block mt-2" onclick="clearForm()">
                                            <i class="fas fa-eraser"></i> Limpiar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <div id="loading" class="text-center" style="display: none;">
                        <div class="spinner-border text-info" role="status">
                            <span class="sr-only">Cargando...</span>
                        </div>
                        <p class="mt-2">Buscando recargas...</p>
                    </div>
                    
                    <div id="rechargesResult" style="display: none;">
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5>Historial de Recargas</h5>
                            <div>
                                <span class="badge badge-info" id="totalResults">0 resultados</span>
                                <span class="badge badge-success" id="totalAmount">Total: S/ 0.00</span>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Monto</th>
                                        <th>Moneda</th>
                                        <th>CCI Origen</th>
                                        <th>CCI Destino</th>
                                        <th>Estado</th>
                                        <th>Referencia</th>
                                    </tr>
                                </thead>
                                <tbody id="rechargesTableBody">
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <button class="btn btn-outline-info" id="prevPage" disabled>
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </button>
                                <button class="btn btn-outline-info" id="nextPage">
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

document.addEventListener('DOMContentLoaded', function() {
    // Establecer fecha por defecto (últimos 7 días incluyendo hoy)
    console.log('Setting default dates for recharges...');
    const today = new Date();
    const sevenDaysAgo = new Date(today);
    sevenDaysAgo.setDate(today.getDate() - 7);
    
    const todayStr = today.toISOString().split('T')[0];
    const sevenDaysAgoStr = sevenDaysAgo.toISOString().split('T')[0];
    
    console.log('Today:', todayStr, 'Seven days ago:', sevenDaysAgoStr);
    
    const endDateField = document.getElementById('endDate');
    const startDateField = document.getElementById('startDate');
    
    if (endDateField && startDateField) {
        endDateField.value = todayStr;
        startDateField.value = sevenDaysAgoStr;
        console.log('Dates set successfully');
    } else {
        console.error('Date fields not found');
    }
    
    // Form submit
    const rechargesForm = document.getElementById('rechargesForm');
    if (rechargesForm) {
        rechargesForm.addEventListener('submit', function(e) {
            e.preventDefault();
            currentPage = 1;
            searchRecharges();
        });
    }
    
    // Pagination buttons
    const prevPageBtn = document.getElementById('prevPage');
    const nextPageBtn = document.getElementById('nextPage');
    
    if (prevPageBtn) {
        prevPageBtn.addEventListener('click', function() {
            if (currentPage > 1) {
                currentPage--;
                searchRecharges();
            }
        });
    }
    
    if (nextPageBtn) {
        nextPageBtn.addEventListener('click', function() {
            currentPage++;
            searchRecharges();
        });
    }
});

function searchRecharges() {
    const formData = {
        startDate: document.getElementById('startDate').value,
        endDate: document.getElementById('endDate').value,
        page: currentPage
    };
    
    if (!formData.startDate || !formData.endDate) {
        alert('Por favor seleccione las fechas de inicio y fin');
        return;
    }
    
    const loading = document.getElementById('loading');
    const rechargesResult = document.getElementById('rechargesResult');
    const errorResult = document.getElementById('errorResult');
    
    if (loading) loading.style.display = 'block';
    if (rechargesResult) rechargesResult.style.display = 'none';
    if (errorResult) errorResult.style.display = 'none';
    
    fetch('<?= base_url('backoffice/recharges') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (loading) loading.style.display = 'none';
        
        if (data.data && data.data.recharges) {
            displayRecharges(data.data);
            if (rechargesResult) rechargesResult.style.display = 'block';
        } else {
            const errorMessage = document.getElementById('errorMessage');
            if (errorMessage) errorMessage.textContent = 'No se encontraron recargas';
            if (errorResult) errorResult.style.display = 'block';
        }
    })
    .catch(error => {
        if (loading) loading.style.display = 'none';
        const errorMessage = document.getElementById('errorMessage');
        if (errorMessage) errorMessage.textContent = 'Error al buscar recargas';
        if (errorResult) errorResult.style.display = 'block';
        console.error('Error:', error);
    });
}

function displayRecharges(data) {
    const recharges = data.recharges || [];
    const total = data.total || 0;
    const totalAmount = data.totalAmount || 0;
    const tbody = document.getElementById('rechargesTableBody');
    const totalResults = document.getElementById('totalResults');
    const totalAmountElement = document.getElementById('totalAmount');
    
    if (tbody) tbody.innerHTML = '';
    if (totalResults) totalResults.textContent = total + ' resultados';
    if (totalAmountElement) totalAmountElement.textContent = 'Total: S/ ' + parseFloat(totalAmount).toFixed(2);
    
    if (recharges.length === 0) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center">No se encontraron recargas</td></tr>';
        return;
    }
    
    recharges.forEach(function(recharge) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${recharge.id || 'N/A'}</td>
            <td>${formatDate(recharge.date)}</td>
            <td><span class="badge badge-info">${recharge.type || 'Recarga'}</span></td>
            <td class="text-right font-weight-bold text-success">
                + ${formatAmount(recharge.amount)}
            </td>
            <td>${recharge.currency || 'PEN'}</td>
            <td><small>${recharge.sourceAccount || 'N/A'}</small></td>
            <td><small>${recharge.destinationAccount || 'N/A'}</small></td>
            <td><span class="badge badge-${getStatusBadge(recharge.status)}">${recharge.status || 'Completado'}</span></td>
            <td><small>${recharge.reference || 'N/A'}</small></td>
        `;
        if (tbody) tbody.appendChild(row);
    });
    
    // Actualizar controles de paginación
    const prevPage = document.getElementById('prevPage');
    const nextPage = document.getElementById('nextPage');
    const pageInfo = document.getElementById('pageInfo');
    
    if (prevPage) prevPage.disabled = currentPage <= 1;
    if (nextPage) nextPage.disabled = recharges.length < 10; // Asumiendo 10 por página
    if (pageInfo) pageInfo.textContent = 'Página ' + currentPage;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-PE') + ' ' + date.toLocaleTimeString('es-PE', {hour: '2-digit', minute: '2-digit'});
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

function clearForm() {
    const form = document.getElementById('rechargesForm');
    const rechargesResult = document.getElementById('rechargesResult');
    const errorResult = document.getElementById('errorResult');
    
    if (form) form.reset();
    if (rechargesResult) rechargesResult.style.display = 'none';
    if (errorResult) errorResult.style.display = 'none';
    currentPage = 1;
    
    // Reestablecer fechas por defecto (últimos 7 días incluyendo hoy)
    const today = new Date();
    const sevenDaysAgo = new Date(today);
    sevenDaysAgo.setDate(today.getDate() - 7);
    
    const todayStr = today.toISOString().split('T')[0];
    const sevenDaysAgoStr = sevenDaysAgo.toISOString().split('T')[0];
    
    const endDate = document.getElementById('endDate');
    const startDate = document.getElementById('startDate');
    
    if (endDate) endDate.value = todayStr;
    if (startDate) startDate.value = sevenDaysAgoStr;
}
</script>
<?= $this->endSection() ?>