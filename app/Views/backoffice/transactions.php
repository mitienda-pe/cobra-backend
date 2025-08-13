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
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            Se listarán las transacciones de la cuenta de la organización seleccionada automáticamente.
                        </div>
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
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="page">Página</label>
                                    <input type="number" class="form-control" id="page" name="page" value="1" min="1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Buscar Transacciones
                                        </button>
                                        <button type="button" class="btn btn-secondary ml-2" onclick="clearForm()">
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
                                        <th>Tipo</th>
                                        <th>CCI Contraparte</th>
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

document.addEventListener('DOMContentLoaded', function() {
    // Usar setTimeout para asegurar que el DOM esté completamente listo
    setTimeout(function() {
        // Establecer fecha por defecto (últimos 7 días incluyendo hoy)
        console.log('Setting default dates for transactions...');
        const today = new Date();
        const sevenDaysAgo = new Date(today);
        sevenDaysAgo.setDate(today.getDate() - 7);
        
        const todayStr = today.toISOString().split('T')[0];
        const sevenDaysAgoStr = sevenDaysAgo.toISOString().split('T')[0];
        
        console.log('Today:', todayStr, 'Seven days ago:', sevenDaysAgoStr);
        
        const endDateField = document.getElementById('endDate');
        const startDateField = document.getElementById('startDate');
        
        console.log('endDateField found:', !!endDateField);
        console.log('startDateField found:', !!startDateField);
        
        if (endDateField && startDateField) {
            endDateField.value = todayStr;
            startDateField.value = sevenDaysAgoStr;
            console.log('Dates set successfully');
            console.log('endDate value:', endDateField.value);
            console.log('startDate value:', startDateField.value);
        } else {
            console.error('Date fields not found');
            console.error('endDateField:', endDateField);
            console.error('startDateField:', startDateField);
        }
    }, 100);
    
    
    // Form submit
    const transactionsForm = document.getElementById('transactionsForm');
    if (transactionsForm) {
        transactionsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            currentPage = 1;
            searchTransactions();
        });
    }
    
    // Pagination buttons
    const prevPageBtn = document.getElementById('prevPage');
    const nextPageBtn = document.getElementById('nextPage');
    
    if (prevPageBtn) {
        prevPageBtn.addEventListener('click', function() {
            if (currentPage > 1) {
                currentPage--;
                searchTransactions();
            }
        });
    }
    
    if (nextPageBtn) {
        nextPageBtn.addEventListener('click', function() {
            currentPage++;
            searchTransactions();
        });
    }
    
});

function searchTransactions() {
    const formData = {
        startDate: document.getElementById('startDate').value,
        endDate: document.getElementById('endDate').value,
        page: currentPage
    };
    
    if (!formData.startDate || !formData.endDate) {
        alert('Por favor seleccione las fechas de inicio y fin');
        return;
    }
    
    currentFilters = formData;
    
    const loading = document.getElementById('loading');
    const transactionsResult = document.getElementById('transactionsResult');
    const errorResult = document.getElementById('errorResult');
    
    if (loading) loading.style.display = 'block';
    if (transactionsResult) transactionsResult.style.display = 'none';
    if (errorResult) errorResult.style.display = 'none';
    
    // Obtener token CSRF
    const csrfToken = document.querySelector('meta[name="X-CSRF-TOKEN"]').getAttribute('content');
    formData['csrf_token_name'] = csrfToken;
    
    fetch('<?= base_url('backoffice/transactions') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
        },
        body: new URLSearchParams(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (loading) loading.style.display = 'none';
        
        if (data.data && data.data.transactions) {
            displayTransactions(data.data);
            if (transactionsResult) transactionsResult.style.display = 'block';
        } else {
            const errorMessage = document.getElementById('errorMessage');
            if (errorMessage) errorMessage.textContent = 'No se encontraron transacciones';
            if (errorResult) errorResult.style.display = 'block';
        }
    })
    .catch(error => {
        if (loading) loading.style.display = 'none';
        const errorMessage = document.getElementById('errorMessage');
        if (errorMessage) errorMessage.textContent = 'Error al buscar transacciones';
        if (errorResult) errorResult.style.display = 'block';
        console.error('Error:', error);
    });
}

function displayTransactions(data) {
    const transactions = data.transactions || [];
    const total = data.total || 0;
    const tbody = document.getElementById('transactionsTableBody');
    const totalResults = document.getElementById('totalResults');
    
    if (tbody) tbody.innerHTML = '';
    if (totalResults) totalResults.textContent = total + ' resultados';
    
    if (transactions.length === 0) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center">No se encontraron transacciones</td></tr>';
        return;
    }
    
    transactions.forEach(function(transaction) {
        const row = document.createElement('tr');
        const transactionType = transaction.debtorCCI === transaction.creditorCCI ? 'Interno' : 
                               (transaction.type || 'Transferencia');
        const counterparty = transaction.debtorCCI !== transaction.creditorCCI ? 
                            (transaction.creditorCCI || transaction.debtorCCI || 'N/A') : 'Cuenta propia';
        
        row.innerHTML = `
            <td>${transaction.id || 'N/A'}</td>
            <td>${formatDate(transaction.date)}</td>
            <td><span class="badge badge-info">${transactionType}</span></td>
            <td><small>${counterparty}</small></td>
            <td class="text-right">${formatAmount(transaction.amount)}</td>
            <td>${transaction.currency || 'PEN'}</td>
            <td><span class="badge badge-${getStatusBadge(transaction.status)}">${transaction.status || 'Pendiente'}</span></td>
            <td>
                <a href="<?= base_url('backoffice/transaction-detail') ?>/${transaction.id}" 
                   class="btn btn-sm btn-info" title="Ver Detalle">
                    <i class="fas fa-eye"></i>
                </a>
            </td>
        `;
        if (tbody) tbody.appendChild(row);
    });
    
    // Actualizar controles de paginación
    const prevPage = document.getElementById('prevPage');
    const nextPage = document.getElementById('nextPage');
    const pageInfo = document.getElementById('pageInfo');
    
    if (prevPage) prevPage.disabled = currentPage <= 1;
    if (nextPage) nextPage.disabled = transactions.length < 10; // Asumiendo 10 por página
    if (pageInfo) pageInfo.textContent = 'Página ' + currentPage;
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
    const form = document.getElementById('transactionsForm');
    const transactionsResult = document.getElementById('transactionsResult');
    const errorResult = document.getElementById('errorResult');
    
    if (form) form.reset();
    if (transactionsResult) transactionsResult.style.display = 'none';
    if (errorResult) errorResult.style.display = 'none';
    currentPage = 1;
    
    // Usar setTimeout para asegurar que el reset no interfiera
    setTimeout(function() {
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
    }, 50);
}
</script>
<?= $this->endSection() ?>