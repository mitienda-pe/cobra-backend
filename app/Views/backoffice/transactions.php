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
                            Se listarán las transacciones usando las credenciales centralizadas de Ligo.
                        </div>

                        <?php
                        // Show active Ligo configuration
                        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
                        $activeConfig = $superadminLigoConfigModel->where('enabled', 1)->where('is_active', 1)->first();
                        ?>
                        <?php if ($activeConfig): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-gear-wide-connected"></i>
                                <strong>Configuración activa:</strong>
                                <span class="badge bg-<?= $activeConfig['environment'] === 'prod' ? 'danger' : 'warning' ?> ms-1">
                                    <?= strtoupper($activeConfig['environment']) ?>
                                </span>
                                <small class="d-block mt-1">
                                    Usuario: <code><?= esc($activeConfig['username']) ?></code> |
                                    Company: <code><?= esc(substr($activeConfig['company_id'], 0, 8)) ?>...</code>
                                </small>
                            </div>
                        <?php endif; ?>
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
                                <span class="badge bg-info text-white" id="totalResults">0 resultados</span>
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

                if (data.data && data.data.records && data.data.records.length > 0) {
                    displayTransactions(data.data);
                    if (transactionsResult) transactionsResult.style.display = 'block';
                } else {
                    const errorMessage = document.getElementById('errorMessage');
                    if (errorMessage) errorMessage.textContent = 'No se encontraron transacciones en el rango de fechas seleccionado';
                    if (errorResult) errorResult.style.display = 'block';
                }
            })
            .catch(error => {
                if (loading) loading.style.display = 'none';
                const errorMessage = document.getElementById('errorMessage');
                if (errorMessage) errorMessage.textContent = error.message || 'Error al buscar transacciones';
                if (errorResult) errorResult.style.display = 'block';
                console.error('Error:', error);
            });
    }

    function displayTransactions(data) {
        const transactions = data.records || [];
        const total = data.detail ? data.detail.totalRecords : 0;
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
            const transactionType = transaction.type || 'Transferencia';
            const counterparty = transaction.type === 'recharge' ? transaction.debtorCCI :
                (transaction.creditorCCI || transaction.debtorCCI || 'N/A');
            const counterpartyName = transaction.type === 'recharge' ? transaction.debtorName :
                (transaction.creditorName || 'N/A');
            const currency = transaction.currency === '604' ? 'PEN' :
                transaction.currency === '840' ? 'USD' : 'PEN';
            const status = transaction.responseCode === '00' ? 'Completado' : 'Error';

            row.innerHTML = `
            <td><small>${transaction.transferId || transaction.instructionId || 'N/A'}</small></td>
            <td>${formatDate(transaction.createdAt)}</td>
            <td><span class="badge bg-${getTypeBadge(transaction.type)}">${transactionType}</span></td>
            <td>
                <small><strong>${counterparty}</strong><br>
                ${counterpartyName}</small>
            </td>
            <td class="text-right"><strong>${formatAmount(transaction.amount)}</strong></td>
            <td>${currency}</td>
            <td><span class="badge bg-${getStatusBadge(transaction.responseCode)}">${status}</span></td>
            <td>
                <button class="btn btn-sm btn-info" onclick="showTransactionDetail('${transaction.transferId || transaction.instructionId}')" title="Ver Detalle">
                    <i class="fas fa-eye"></i>
                </button>
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

    function getStatusBadge(responseCode) {
        return responseCode === '00' ? 'success' : 'danger';
    }

    function getTypeBadge(type) {
        switch (type) {
            case 'recharge':
                return 'success';
            case 'transfer':
                return 'primary';
            default:
                return 'info';
        }
    }

    function showTransactionDetail(transactionId) {
        alert('Detalle de transacción: ' + transactionId);
        // Aquí se puede implementar un modal o redirección
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