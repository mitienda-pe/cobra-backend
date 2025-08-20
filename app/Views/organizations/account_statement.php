<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line mr-2"></i>
                        Estado de Cuenta Ligo (Producción) - <?= esc($organization['name']) ?>
                    </h3>
                    <div class="btn-group">
                        <button type="button" class="btn btn-info btn-sm" onclick="recalculateBalance()">
                            <i class="fas fa-sync-alt"></i> Recalcular
                        </button>
                        <a href="<?= site_url('organizations/account/' . $organization['uuid'] . '/movements') ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-list"></i> Ver Movimientos
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <form method="GET" class="form-inline">
                                <div class="form-group mr-3">
                                    <label for="date_start" class="mr-2">Desde:</label>
                                    <input type="date" class="form-control form-control-sm" id="date_start" name="date_start" 
                                           value="<?= esc($dateStart) ?>">
                                </div>
                                <div class="form-group mr-3">
                                    <label for="date_end" class="mr-2">Hasta:</label>
                                    <input type="date" class="form-control form-control-sm" id="date_end" name="date_end" 
                                           value="<?= esc($dateEnd) ?>">
                                </div>
                                <div class="form-group mr-3">
                                    <label for="currency" class="mr-2">Moneda:</label>
                                    <select class="form-control form-control-sm" id="currency" name="currency">
                                        <option value="PEN" <?= $currency === 'PEN' ? 'selected' : '' ?>>Soles (PEN)</option>
                                        <option value="USD" <?= $currency === 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-filter"></i> Filtrar
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Balance Summary Cards -->
                    <div class="row">
                        <div class="col-lg-4 col-md-6">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Total Recaudado (Ligo Prod)</h5>
                                            <h3 class="mb-0">
                                                <?= $currency ?> <?= number_format($balance['total_collected'] ?? 0, 2) ?>
                                            </h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-qrcode fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Transacciones</h5>
                                            <h3 class="mb-0">
                                                <?= isset($ligoSummary['total_transactions']) ? number_format($ligoSummary['total_transactions']) : '0' ?>
                                            </h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-list fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Promedio por Pago</h5>
                                            <h3 class="mb-0">
                                                <?= $currency ?> <?= isset($ligoSummary['average_amount']) ? number_format($ligoSummary['average_amount'], 2) : '0.00' ?>
                                            </h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-calculator fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ligo Payments Summary (for selected date range) -->
                    <?php if ($ligoSummary && $ligoSummary['total_transactions'] > 0): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5>Resumen de Pagos Ligo Producción (<?= esc($dateStart) ?> - <?= esc($dateEnd) ?>)</h5>
                            <div class="row">
                                <div class="col-md-2 col-sm-6">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-primary"><i class="fas fa-hashtag"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Transacciones</span>
                                            <span class="info-box-number"><?= number_format($ligoSummary['total_transactions']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-6">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-success"><i class="fas fa-dollar-sign"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Total</span>
                                            <span class="info-box-number"><?= number_format($ligoSummary['total_amount'], 2) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-6">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-info"><i class="fas fa-chart-bar"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Promedio</span>
                                            <span class="info-box-number"><?= number_format($ligoSummary['average_amount'], 2) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-secondary"><i class="fas fa-calendar-alt"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Primer Pago</span>
                                            <span class="info-box-number text-sm">
                                                <?= $ligoSummary['first_payment'] ? date('d/m/Y', strtotime($ligoSummary['first_payment'])) : '-' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-secondary"><i class="fas fa-calendar-check"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Último Pago</span>
                                            <span class="info-box-number text-sm">
                                                <?= $ligoSummary['last_payment'] ? date('d/m/Y', strtotime($ligoSummary['last_payment'])) : '-' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Monthly Breakdown Chart -->
                    <?php if ($monthlyBreakdown && count($monthlyBreakdown) > 0): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5>Pagos Ligo por Mes (<?= date('Y') ?>)</h5>
                            <div class="card">
                                <div class="card-body">
                                    <canvas id="monthlyChart" height="100"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Transfers Section -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-exchange-alt mr-2"></i>
                                        Transferencias y Transacciones Ligo
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <!-- Transfer Balance Summary -->
                                    <?php if (isset($transferBalance)): ?>
                                    <div class="row mb-4">
                                        <div class="col-md-4">
                                            <div class="info-box bg-success">
                                                <span class="info-box-icon"><i class="fas fa-arrow-down"></i></span>
                                                <div class="info-box-content">
                                                    <span class="info-box-text">Ingresos por Transferencia</span>
                                                    <span class="info-box-number">S/ <?= number_format($transferBalance['incoming'], 2) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="info-box bg-danger">
                                                <span class="info-box-icon"><i class="fas fa-arrow-up"></i></span>
                                                <div class="info-box-content">
                                                    <span class="info-box-text">Retiros</span>
                                                    <span class="info-box-number">S/ <?= number_format($transferBalance['withdrawals'], 2) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="info-box bg-info">
                                                <span class="info-box-icon"><i class="fas fa-wallet"></i></span>
                                                <div class="info-box-content">
                                                    <span class="info-box-text">Balance Disponible</span>
                                                    <span class="info-box-number">S/ <?= number_format($transferBalance['available_balance'], 2) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Tabs for Transfers and Ligo Transactions -->
                                    <ul class="nav nav-tabs" id="accountTabs" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" id="transfers-tab" data-toggle="tab" href="#transfers" role="tab">
                                                <i class="fas fa-exchange-alt"></i> Transferencias Internas
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="ligo-transactions-tab" data-toggle="tab" href="#ligo-transactions" role="tab">
                                                <i class="fas fa-qrcode"></i> Transacciones Ligo
                                            </a>
                                        </li>
                                    </ul>
                                    
                                    <div class="tab-content" id="accountTabsContent">
                                        <!-- Transfers Tab -->
                                        <div class="tab-pane fade show active" id="transfers" role="tabpanel">
                                            <div class="mt-3">
                                                <h6>Últimas 20 Transferencias</h6>
                                                <?php if (!empty($transfers)): ?>
                                                <div class="table-responsive">
                                                    <table class="table table-striped table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Fecha</th>
                                                                <th>Tipo</th>
                                                                <th>CCI Destino</th>
                                                                <th>Beneficiario</th>
                                                                <th>Monto</th>
                                                                <th>Estado</th>
                                                                <th>Concepto</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($transfers as $transfer): ?>
                                                            <tr>
                                                                <td><?= date('d/m/Y H:i', strtotime($transfer['created_at'])) ?></td>
                                                                <td>
                                                                    <span class="badge badge-<?= $transfer['transfer_type'] === 'withdrawal' ? 'danger' : 'primary' ?>">
                                                                        <?= ucfirst($transfer['transfer_type']) ?>
                                                                    </span>
                                                                </td>
                                                                <td><?= esc($transfer['creditor_cci']) ?></td>
                                                                <td><?= esc($transfer['creditor_name']) ?></td>
                                                                <td>S/ <?= number_format($transfer['amount'], 2) ?></td>
                                                                <td>
                                                                    <?php
                                                                    $statusClass = [
                                                                        'completed' => 'success',
                                                                        'processing' => 'warning',
                                                                        'pending' => 'info',
                                                                        'failed' => 'danger'
                                                                    ];
                                                                    ?>
                                                                    <span class="badge badge-<?= $statusClass[$transfer['status']] ?? 'secondary' ?>">
                                                                        <?= ucfirst($transfer['status']) ?>
                                                                    </span>
                                                                </td>
                                                                <td><?= esc($transfer['unstructured_information']) ?></td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <?php else: ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle mr-2"></i>
                                                    No hay transferencias registradas para esta organización.
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Ligo Transactions Tab -->
                                        <div class="tab-pane fade" id="ligo-transactions" role="tabpanel">
                                            <div class="mt-3">
                                                <!-- Filters -->
                                                <form id="ligoTransactionsForm" class="mb-3">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <label for="ligoStartDate">Fecha Inicio:</label>
                                                            <input type="date" class="form-control form-control-sm" id="ligoStartDate" name="startDate" required>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label for="ligoEndDate">Fecha Fin:</label>
                                                            <input type="date" class="form-control form-control-sm" id="ligoEndDate" name="endDate" required>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label>&nbsp;</label><br>
                                                            <button type="submit" class="btn btn-primary btn-sm">
                                                                <i class="fas fa-search"></i> Buscar
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>

                                                <!-- Transactions Table -->
                                                <div id="ligoTransactionsResult" style="display: none;">
                                                    <div class="table-responsive">
                                                        <table class="table table-striped table-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th>ID</th>
                                                                    <th>Fecha</th>
                                                                    <th>Tipo</th>
                                                                    <th>CCI Contraparte</th>
                                                                    <th>Monto</th>
                                                                    <th>Moneda</th>
                                                                    <th>Estado</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="ligoTransactionsTableBody">
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                                        <div>
                                                            <button class="btn btn-outline-primary btn-sm" id="ligoPrevPage" disabled>
                                                                <i class="fas fa-chevron-left"></i> Anterior
                                                            </button>
                                                            <button class="btn btn-outline-primary btn-sm" id="ligoNextPage">
                                                                Siguiente <i class="fas fa-chevron-right"></i>
                                                            </button>
                                                        </div>
                                                        <span id="ligoPageInfo">Página 1</span>
                                                    </div>
                                                </div>
                                                
                                                <div id="ligoErrorResult" class="alert alert-danger" style="display: none;">
                                                    <h6><i class="fas fa-exclamation-triangle"></i> Error</h6>
                                                    <p id="ligoErrorMessage"></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Balance Info -->
                    <?php if ($balance): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Información del Balance:</strong><br>
                                Último pago registrado: <?= $balance['last_payment_date'] ? date('d/m/Y H:i', strtotime($balance['last_payment_date'])) : 'Ninguno' ?><br>
                                Última actualización: <?= $balance['last_calculated_at'] ? date('d/m/Y H:i', strtotime($balance['last_calculated_at'])) : 'Nunca calculado' ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Cargando...</span>
                </div>
                <p class="mt-2">Recalculando balance...</p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Monthly breakdown chart
<?php if ($monthlyBreakdown && count($monthlyBreakdown) > 0): ?>
const ctx = document.getElementById('monthlyChart').getContext('2d');
const monthlyChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: [<?= implode(',', array_map(function($item) { return '"' . $item['month_name'] . '"'; }, $monthlyBreakdown)) ?>],
        datasets: [{
            label: 'Monto Total (<?= esc($currency) ?>)',
            data: [<?= implode(',', array_column($monthlyBreakdown, 'total_amount')) ?>],
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }, {
            label: 'Número de Transacciones',
            data: [<?= implode(',', array_column($monthlyBreakdown, 'transaction_count')) ?>],
            type: 'line',
            backgroundColor: 'rgba(255, 99, 132, 0.6)',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 2,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Monto (<?= esc($currency) ?>)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Transacciones'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        },
        plugins: {
            title: {
                display: true,
                text: 'Pagos Ligo por Mes - <?= date('Y') ?>'
            }
        }
    }
});
<?php endif; ?>

// Recalculate balance function
function recalculateBalance() {
    $('#loadingModal').modal('show');
    
    fetch('<?= site_url('organizations/account/' . $organization['uuid'] . '/recalculate') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            currency: '<?= esc($currency) ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        $('#loadingModal').modal('hide');
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        $('#loadingModal').modal('hide');
        console.error('Error:', error);
        alert('Error al recalcular el balance');
    });
}

// Ligo Transactions functionality
let ligoCurrentPage = 1;
let ligoCurrentFilters = {};

document.addEventListener('DOMContentLoaded', function() {
    // Set default dates for Ligo transactions (last 7 days)
    const today = new Date();
    const sevenDaysAgo = new Date(today);
    sevenDaysAgo.setDate(today.getDate() - 7);
    
    const todayStr = today.toISOString().split('T')[0];
    const sevenDaysAgoStr = sevenDaysAgo.toISOString().split('T')[0];
    
    const ligoEndDate = document.getElementById('ligoEndDate');
    const ligoStartDate = document.getElementById('ligoStartDate');
    
    if (ligoEndDate && ligoStartDate) {
        ligoEndDate.value = todayStr;
        ligoStartDate.value = sevenDaysAgoStr;
    }
    
    // Form submit for Ligo transactions
    const ligoForm = document.getElementById('ligoTransactionsForm');
    if (ligoForm) {
        ligoForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ligoCurrentPage = 1;
            searchLigoTransactions();
        });
    }
    
    // Pagination buttons for Ligo transactions
    const ligoPrevBtn = document.getElementById('ligoPrevPage');
    const ligoNextBtn = document.getElementById('ligoNextPage');
    
    if (ligoPrevBtn) {
        ligoPrevBtn.addEventListener('click', function() {
            if (ligoCurrentPage > 1) {
                ligoCurrentPage--;
                searchLigoTransactions();
            }
        });
    }
    
    if (ligoNextBtn) {
        ligoNextBtn.addEventListener('click', function() {
            ligoCurrentPage++;
            searchLigoTransactions();
        });
    }
});

function searchLigoTransactions() {
    const startDate = document.getElementById('ligoStartDate').value;
    const endDate = document.getElementById('ligoEndDate').value;
    
    if (!startDate || !endDate) {
        alert('Por favor seleccione ambas fechas');
        return;
    }
    
    ligoCurrentFilters = { startDate, endDate };
    
    const formData = new FormData();
    formData.append('startDate', startDate);
    formData.append('endDate', endDate);
    formData.append('page', ligoCurrentPage);
    
    // Show loading
    document.getElementById('ligoTransactionsResult').style.display = 'none';
    document.getElementById('ligoErrorResult').style.display = 'none';
    
    fetch('<?= site_url('organizations/account/' . $organization['uuid'] . '/ligo-transactions') ?>', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            showLigoError(data.error);
        } else {
            displayLigoTransactions(data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showLigoError('Error al cargar las transacciones');
    });
}

function displayLigoTransactions(data) {
    const tbody = document.getElementById('ligoTransactionsTableBody');
    tbody.innerHTML = '';
    
    if (data.data && data.data.data && data.data.data.length > 0) {
        data.data.data.forEach(transaction => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${transaction.id || '-'}</td>
                <td>${transaction.transactionDate || '-'}</td>
                <td>${transaction.type || '-'}</td>
                <td>${transaction.counterpartyCCI || '-'}</td>
                <td>${transaction.amount ? parseFloat(transaction.amount).toFixed(2) : '0.00'}</td>
                <td>${transaction.currency || 'PEN'}</td>
                <td>
                    <span class="badge badge-${transaction.status === 'COMPLETED' ? 'success' : 'warning'}">
                        ${transaction.status || 'UNKNOWN'}
                    </span>
                </td>
            `;
            tbody.appendChild(row);
        });
        
        document.getElementById('ligoTransactionsResult').style.display = 'block';
        updateLigoPagination(data.data);
    } else {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No se encontraron transacciones</td></tr>';
        document.getElementById('ligoTransactionsResult').style.display = 'block';
        updateLigoPagination(null);
    }
}

function updateLigoPagination(data) {
    const prevBtn = document.getElementById('ligoPrevPage');
    const nextBtn = document.getElementById('ligoNextPage');
    const pageInfo = document.getElementById('ligoPageInfo');
    
    prevBtn.disabled = ligoCurrentPage <= 1;
    
    // Enable/disable next button based on data availability
    if (data && data.data && data.data.length > 0) {
        nextBtn.disabled = false;
    } else {
        nextBtn.disabled = true;
    }
    
    pageInfo.textContent = `Página ${ligoCurrentPage}`;
}

function showLigoError(message) {
    document.getElementById('ligoErrorMessage').textContent = message;
    document.getElementById('ligoErrorResult').style.display = 'block';
    document.getElementById('ligoTransactionsResult').style.display = 'none';
}
</script>
<?= $this->endSection() ?>