<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Factura <?= esc($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Factura <?= esc($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A') ?></h1>
    </div>
    <div class="col text-end">
        <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
            <a href="<?= site_url('invoices/edit/' . $invoice['uuid']) ?>" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <?php if ($invoice['status'] === 'pending'): ?>
                <a href="<?= site_url('invoice/' . $invoice['uuid'] . '/instalments') ?>" class="btn btn-info">
                    <i class="bi bi-list-ol"></i> Cuotas
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (session()->has('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= session('success') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (session()->has('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= session('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Detalles de la Factura</h5>
                <hr>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Número:</strong>
                    </div>
                    <div class="col-md-8">
                        <?= esc($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A') ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Fecha de Emisión:</strong>
                    </div>
                    <div class="col-md-8">
                        <?= isset($invoice['issue_date']) ? date('d/m/Y', strtotime($invoice['issue_date'])) : 'No especificada' ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Cliente:</strong>
                    </div>
                    <div class="col-md-8">
                        <?= esc($client['business_name']) ?><br>
                        <small class="text-muted">
                            RUC/DNI: <?= esc($client['document_number']) ?>
                        </small>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Concepto:</strong>
                    </div>
                    <div class="col-md-8">
                        <?= esc($invoice['concept']) ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Importe:</strong>
                    </div>
                    <div class="col-md-8">
                        S/ <?= number_format($invoice['total_amount'] ?? $invoice['amount'] ?? 0, 2) ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Estado:</strong>
                    </div>
                    <div class="col-md-8">
                        <span class="badge bg-<?= $invoice['status'] === 'paid' ? 'success' : ($invoice['status'] === 'pending' ? 'warning' : ($invoice['status'] === 'cancelled' ? 'danger' : ($invoice['status'] === 'expired' ? 'secondary' : 'info'))) ?>">
                            <?= ucfirst($invoice['status']) ?>
                        </span>
                    </div>
                </div>

                <?php if ($invoice['external_id']): ?>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>ID Externo:</strong>
                        </div>
                        <div class="col-md-8">
                            <?= esc($invoice['external_id']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($invoice['notes']): ?>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Notas:</strong>
                        </div>
                        <div class="col-md-8">
                            <?= nl2br(esc($invoice['notes'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sección de Cuotas -->
        <div class="card mt-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Cuotas y Pagos Registrados</h5>
                </div>
                <hr>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Monto</th>
                                <th>Vencimiento</th>
                                <th>Estado</th>
                                <th>Pagos</th>
                                <th>QR</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Función para convertir pagos a soles consistentemente (fuera del bucle)
                            if (!function_exists('normalizePaymentAmount')) {
                                function normalizePaymentAmount($amount, $paymentMethod)
                                {
                                    // Para pagos QR, convertir de centavos a soles si el monto parece estar en centavos
                                    if ($paymentMethod === 'ligo_qr' && $amount >= 100) {
                                        return $amount / 100;
                                    }
                                    return $amount;
                                }
                            }

                            // Mostrar todas las cuotas en esta vista
                            foreach ($instalments as $instalment):
                                // Calcular clase CSS para la fila
                                $rowClass = '';

                                // Buscar pagos asociados a esta cuota
                                $instalmentPayments = [];
                                $instalmentPaidAmount = 0;
                                foreach ($payments as $payment) {
                                    if (isset($payment['instalment_id']) && $payment['instalment_id'] == $instalment['id']) {
                                        $instalmentPayments[] = $payment;
                                        $paymentAmount = normalizePaymentAmount($payment['amount'], $payment['payment_method']);
                                        $instalmentPaidAmount += $paymentAmount;
                                    }
                                }

                                // Verificar si la cuota está completamente pagada basándose en los pagos reales
                                $instalmentAmount = isset($instalment['amount']) ? $instalment['amount'] : 0;
                                $isPaid = $instalmentPaidAmount >= $instalmentAmount;

                                // Actualizar la clase CSS basándose en el estado real de pagos
                                if ($isPaid) {
                                    $rowClass = 'table-success';
                                } elseif (isset($instalment['is_overdue']) && $instalment['is_overdue']) {
                                    $rowClass = 'table-danger';
                                } elseif (isset($instalment['status']) && $instalment['status'] === 'pending') {
                                    $rowClass = '';
                                }
                            ?>
                                <tr class="<?= $rowClass ?>">
                                    <td><?= isset($instalment['number']) ? $instalment['number'] : 'N/A' ?></td>
                                    <td>S/ <?= number_format(isset($instalment['amount']) ? $instalment['amount'] : 0, 2) ?></td>
                                    <td><?= isset($instalment['due_date']) && $instalment['due_date'] ? date('d/m/Y', strtotime($instalment['due_date'])) : 'N/A' ?></td>
                                    <td>
                                        <span class="badge bg-<?= $isPaid ? 'success' : (isset($instalment['status']) && $instalment['status'] === 'pending' ? 'warning' : (isset($instalment['status']) && $instalment['status'] === 'cancelled' ? 'danger' : (isset($instalment['status']) && $instalment['status'] === 'expired' ? 'secondary' : 'info'))) ?>">
                                            <?= $isPaid ? 'Paid' : (isset($instalment['status']) ? ucfirst($instalment['status']) : 'Unknown') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($instalmentPayments)): ?>
                                            <span class="badge bg-success">
                                                S/ <?= number_format($instalmentPaidAmount, 2) ?>
                                            </span>
                                            <button type="button" class="btn btn-sm btn-link" data-bs-toggle="tooltip" data-bs-html="true"
                                                title="<?php
                                                        $tooltipContent = '';
                                                        foreach ($instalmentPayments as $p) {
                                                            $paymentMethod = '';
                                                            switch ($p['payment_method']) {
                                                                case 'ligo_qr':
                                                                    $paymentMethod = 'QR-Ligo';
                                                                    break;
                                                                case 'cash':
                                                                    $paymentMethod = 'Efectivo';
                                                                    break;
                                                                case 'transfer':
                                                                    $paymentMethod = 'Transferencia';
                                                                    break;
                                                                case 'deposit':
                                                                    $paymentMethod = 'Depósito';
                                                                    break;
                                                                case 'check':
                                                                    $paymentMethod = 'Cheque';
                                                                    break;
                                                                case 'card':
                                                                    $paymentMethod = 'Tarjeta';
                                                                    break;
                                                                default:
                                                                    $paymentMethod = ucfirst($p['payment_method']);
                                                                    break;
                                                            }

                                                            // Convertir centavos a soles para mostrar en tooltip
                                                            $displayAmount = normalizePaymentAmount($p['amount'], $p['payment_method']);

                                                            $tooltipContent .= date('d/m/Y', strtotime($p['payment_date'])) . ': ' .
                                                                'S/ ' . number_format($displayAmount, 2) .
                                                                ' (' . $paymentMethod;
                                                            if (!empty($p['reference_code'])) {
                                                                $tooltipContent .= ' - Ref: ' . substr($p['reference_code'], 0, 10) . '..';
                                                            }
                                                            $tooltipContent .= ')<br>';
                                                        }
                                                        echo $tooltipContent;
                                                        ?>">
                                                <i class="bi bi-info-circle"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($qrHashesByInstalment[$instalment['id']])):
                                            $qrHash = $qrHashesByInstalment[$instalment['id']]; ?>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-info me-2">QR</span>
                                                <small class="text-muted" title="ID QR: <?= esc($qrHash['id_qr'] ?? 'N/A') ?>">
                                                    <?= substr($qrHash['id_qr'] ?? 'N/A', -8) ?>
                                                </small>
                                            </div>
                                            <div class="mt-1">
                                                <a href="<?= site_url('ligo/hashes') ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                                    <i class="bi bi-eye"></i> Ver
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($instalment['status'] === 'pending' && $auth->hasAnyRole(['superadmin', 'admin'])): ?>
                                            <?php if (!isset($instalment['is_virtual']) && isset($instalment['can_be_paid']) && $instalment['can_be_paid'] && !$isPaid): ?>
                                                <div class="btn-group">
                                                    <a href="<?= site_url('payments/create/' . $invoice['uuid'] . '/' . $instalment['id']) ?>" class="btn btn-sm btn-success">
                                                        <i class="bi bi-cash"></i> Pagar
                                                    </a>
                                                    <?php
                                                    // Get organization to check if Ligo is enabled
                                                    $organizationModel = new \App\Models\OrganizationModel();
                                                    $organization = $organizationModel->find($invoice['organization_id']);
                                                    if (isset($organization['ligo_enabled']) && $organization['ligo_enabled']): ?>
                                                        <a href="<?= site_url('payment/ligo/qr/' . $invoice['uuid'] . '/' . $instalment['id']) ?>" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-qr-code"></i> QR
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif (!isset($instalment['is_virtual']) && isset($instalment['can_be_paid']) && !$instalment['can_be_paid'] && !$isPaid): ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="No se puede pagar esta cuota hasta que se paguen las anteriores">
                                                    <i class="bi bi-cash"></i> Pagar
                                                </button>
                                            <?php elseif (isset($instalment['is_virtual']) && !$isPaid): ?>
                                                <div class="btn-group">
                                                    <a href="<?= site_url('payments/create/' . $invoice['uuid']) ?>" class="btn btn-sm btn-success">
                                                        <i class="bi bi-cash"></i> Pagar
                                                    </a>
                                                    <?php
                                                    // Get organization to check if Ligo is enabled
                                                    $organizationModel = new \App\Models\OrganizationModel();
                                                    $organization = $organizationModel->find($invoice['organization_id']);
                                                    if (isset($organization['ligo_enabled']) && $organization['ligo_enabled']): ?>
                                                        <a href="<?= site_url('payment/ligo/qr/' . $invoice['uuid']) ?>" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-qr-code"></i> QR
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <?php /* Eliminamos el card de Acciones */ ?>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Información del Cliente</h5>
                <hr>

                <div class="mb-2">
                    <strong>Nombre / Razón Social:</strong><br>
                    <?= esc($client['business_name']) ?>
                </div>

                <div class="mb-2">
                    <strong>RUC/DNI:</strong><br>
                    <?= esc($client['document_number']) ?>
                </div>

                <?php if (isset($client['contact_name']) && $client['contact_name']): ?>
                    <div class="mb-2">
                        <strong>Contacto:</strong><br>
                        <?= esc($client['contact_name']) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($client['contact_phone']) && $client['contact_phone']): ?>
                    <div class="mb-2">
                        <strong>Teléfono:</strong><br>
                        <?= esc($client['contact_phone']) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($client['address']) && $client['address']): ?>
                    <div class="mb-2">
                        <strong>Dirección:</strong><br>
                        <?= esc($client['address']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
<?= $this->endSection() ?>