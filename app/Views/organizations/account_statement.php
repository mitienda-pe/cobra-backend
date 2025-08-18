<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line mr-2"></i>
                        Estado de Cuenta - <?= esc($organization['name']) ?>
                    </h3>
                    <div class="btn-group">
                        <button type="button" class="btn btn-info btn-sm" onclick="recalculateBalance()">
                            <i class="fas fa-sync-alt"></i> Recalcular
                        </button>
                        <a href="<?= site_url('organizations/account/' . $organization['id'] . '/movements') ?>" class="btn btn-primary btn-sm">
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
                        <div class="col-lg-3 col-md-6">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Total Recaudado</h5>
                                            <h3 class="mb-0">
                                                <?= $currency ?> <?= number_format($balance['total_collected'] ?? 0, 2) ?>
                                            </h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-coins fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Pagos Ligo</h5>
                                            <h3 class="mb-0">
                                                <?= $currency ?> <?= number_format($balance['total_ligo_payments'] ?? 0, 2) ?>
                                            </h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-qrcode fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Pagos en Efectivo</h5>
                                            <h3 class="mb-0">
                                                <?= $currency ?> <?= number_format($balance['total_cash_payments'] ?? 0, 2) ?>
                                            </h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-money-bill fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Pendiente</h5>
                                            <h3 class="mb-0">
                                                <?= $currency ?> <?= number_format($balance['total_pending'] ?? 0, 2) ?>
                                            </h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-clock fa-2x opacity-75"></i>
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
                            <h5>Resumen de Pagos Ligo (<?= esc($dateStart) ?> - <?= esc($dateEnd) ?>)</h5>
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
    
    fetch('<?= site_url('organizations/account/' . $organization['id'] . '/recalculate') ?>', {
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
</script>
<?= $this->endSection() ?>