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

                    <!-- Ligo Environment Info -->
                    <?php if (isset($activeConfig)): ?>
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle"></i>
                        <strong>Configuración Ligo activa:</strong> 
                        <span class="badge" style="background-color: <?= $isProduction ? '#dc3545' : '#ffc107' ?>; color: <?= $isProduction ? 'white' : 'black' ?>; padding: 0.25rem 0.5rem;">
                            <?= strtoupper($activeConfig['environment']) ?>
                        </span>
                        <small class="d-block mt-1">
                            Mostrando solo pagos QR Ligo de ambiente <strong><?= $isProduction ? 'PRODUCCIÓN' : 'DESARROLLO' ?></strong>
                        </small>
                    </div>
                    <?php endif; ?>

                    <!-- Current Balance Summary -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-box bg-success">
                                <span class="info-box-icon"><i class="fas fa-wallet"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Balance Actual</span>
                                    <span class="info-box-number">S/ <?= number_format($transferBalance['available_balance'] ?? 0, 2) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-info">
                                <span class="info-box-icon"><i class="fas fa-arrow-down"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Ingresos</span>
                                    <span class="info-box-number">S/ <?= number_format($transferBalance['incoming'] ?? 0, 2) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-warning">
                                <span class="info-box-icon"><i class="fas fa-arrow-up"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Salidas</span>
                                    <span class="info-box-number">S/ <?= number_format($transferBalance['withdrawals'] ?? 0, 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Unified Movements Table -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-list mr-2"></i>
                                        Movimientos de Dinero
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Tipo</th>
                                                    <th>Descripción</th>
                                                    <th>Referencia</th>
                                                    <th class="text-right">Monto</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                // Combine and sort all movements
                                                $allMovements = [];
                                                
                                                // Add completed transfers (only successful ones)
                                                if (!empty($transfers)) {
                                                    foreach ($transfers as $transfer) {
                                                        if ($transfer['status'] === 'completed') {
                                                            // For this organization, all transfers are outgoing (withdrawals)
                                                            // The transfer_type "regular" means normal outgoing transfer
                                                            $isWithdrawal = true; // All transfers from this org are outgoing
                                                            
                                                            // Add main transfer
                                                            $allMovements[] = [
                                                                'date' => $transfer['created_at'],
                                                                'type' => 'Transferencia Enviada',
                                                                'description' => 'A: ' . $transfer['creditor_name'] . ' - ' . $transfer['unstructured_information'],
                                                                'reference' => 'CCI: ' . $transfer['creditor_cci'],
                                                                'amount' => $transfer['amount'],
                                                                'status' => 'Completado',
                                                                'is_withdrawal' => $isWithdrawal
                                                            ];
                                                            
                                                            // Add fee as separate row if exists
                                                            if (!empty($transfer['fee_amount']) && $transfer['fee_amount'] > 0) {
                                                                $allMovements[] = [
                                                                    'date' => $transfer['created_at'],
                                                                    'type' => 'Comisión Transferencia',
                                                                    'description' => 'Comisión por envío a ' . $transfer['creditor_name'],
                                                                    'reference' => 'ID: ' . $transfer['id'],
                                                                    'amount' => $transfer['fee_amount'],
                                                                    'status' => 'Completado',
                                                                    'is_withdrawal' => true // Fees are always outgoing
                                                                ];
                                                            }
                                                        }
                                                    }
                                                }
                                                
                                                // Add individual Ligo payments (recharges) - these are always income
                                                if (!empty($ligoPayments)) {
                                                    foreach ($ligoPayments as $payment) {
                                                        // Normalize Ligo amounts based on patterns in the data
                                                        $amount = floatval($payment['amount']);
                                                        
                                                        // If amount is >= 100, it's likely in centavos (like 500 = 5.00 soles)
                                                        // If amount is < 100, it's likely already in soles (like 2, 3, 5)
                                                        if ($amount >= 100) {
                                                            $normalizedAmount = $amount / 100;
                                                        } else {
                                                            $normalizedAmount = $amount;
                                                        }
                                                        
                                                        // Format payment date
                                                        $paymentDate = $payment['payment_date'] ?? $payment['created_at'];
                                                        
                                                        // Use actual environment from payment record
                                                        $paymentEnv = $payment['ligo_environment'] ?? 'dev';
                                                        $envLabel = strtoupper($paymentEnv);
                                                        $envDescription = $paymentEnv === 'prod' ? 'Producción' : 'Desarrollo';
                                                        
                                                        $allMovements[] = [
                                                            'date' => $paymentDate,
                                                            'type' => 'Pago Ligo QR (' . $envLabel . ')',
                                                            'description' => 'Pago de cuota recibido via QR Ligo ' . $envDescription,
                                                            'reference' => 'ID: ' . $payment['id'] . (!empty($payment['external_id']) ? ' | Ext: ' . $payment['external_id'] : ''),
                                                            'amount' => $normalizedAmount,
                                                            'status' => 'Completado',
                                                            'is_withdrawal' => false // Payments are always income
                                                        ];
                                                    }
                                                }
                                                
                                                // Sort by date (newest first)
                                                usort($allMovements, function($a, $b) {
                                                    return strtotime($b['date']) - strtotime($a['date']);
                                                });
                                                
                                                if (!empty($allMovements)): 
                                                    foreach ($allMovements as $movement): 
                                                ?>
                                                <tr>
                                                    <td><?= date('d/m/Y H:i', strtotime($movement['date'])) ?></td>
                                                    <td>
                                                        <span class="badge" style="background-color: <?= $movement['is_withdrawal'] ? '#dc3545' : '#17a2b8' ?>; color: white; padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                            <?= $movement['type'] ?>
                                                        </span>
                                                    </td>
                                                    <td><?= esc($movement['description']) ?></td>
                                                    <td><small class="text-muted"><?= esc($movement['reference']) ?></small></td>
                                                    <td class="text-right">
                                                        <span class="<?= $movement['is_withdrawal'] ? 'text-danger' : 'text-success' ?> font-weight-bold">
                                                            <?= $movement['is_withdrawal'] ? '-' : '+' ?>S/ <?= number_format(abs($movement['amount']), 2) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge" style="background-color: #28a745; color: white; padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                            <?= $movement['status'] ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php 
                                                    endforeach; 
                                                else: 
                                                ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        <i class="fas fa-info-circle mr-2"></i>
                                                        No hay movimientos registrados
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
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

<?= $this->section('styles') ?>
<style>
/* Custom badge styles with proper contrast */
.badge {
    padding: 0.25rem 0.5rem !important;
    font-size: 0.75rem !important;
    font-weight: 600 !important;
    border-radius: 0.25rem !important;
}

.badge-info {
    background-color: #17a2b8 !important;
    color: #ffffff !important;
    border: 1px solid #17a2b8 !important;
}

.badge-warning {
    background-color: #ffc107 !important;
    color: #000000 !important;
    border: 1px solid #ffc107 !important;
}

.badge-success {
    background-color: #28a745 !important;
    color: #ffffff !important;
    border: 1px solid #28a745 !important;
}

.badge-danger {
    background-color: #dc3545 !important;
    color: #ffffff !important;
    border: 1px solid #dc3545 !important;
}

.badge-primary {
    background-color: #007bff !important;
    color: #ffffff !important;
    border: 1px solid #007bff !important;
}

.badge-secondary {
    background-color: #6c757d !important;
    color: #ffffff !important;
    border: 1px solid #6c757d !important;
}

/* Text color classes for amounts */
.text-success {
    color: #28a745 !important;
}

.text-danger {
    color: #dc3545 !important;
}

.font-weight-bold {
    font-weight: 700 !important;
}
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
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

// Simple initialization - no complex JavaScript needed for the unified table
</script>
<?= $this->endSection() ?>