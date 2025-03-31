<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Cuotas de la Factura #<?= $invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A' ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid px-4">
    <h1 class="mt-4">Cuotas de la Factura #<?= $invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A' ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('invoices') ?>">Facturas</a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('invoices/view/' . $invoice['uuid']) ?>">Factura #<?= $invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A' ?></a></li>
        <li class="breadcrumb-item active">Cuotas</li>
    </ol>
    
    <?php if (session()->getFlashdata('success')) : ?>
        <div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
    <?php endif; ?>
    
    <?php if (session()->getFlashdata('error')) : ?>
        <div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-info-circle me-1"></i>
            Información de la Factura
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Cliente:</strong> <?= $client['business_name'] ?></p>
                    <p><strong>Documento:</strong> <?= $client['document_number'] ?></p>
                    <p><strong>Fecha de Emisión:</strong> <?= date('d/m/Y', strtotime($invoice['issue_date'])) ?></p>
                    <p><strong>Fecha de Vencimiento:</strong> <?= date('d/m/Y', strtotime($invoice['due_date'])) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Monto Total:</strong> <?= $invoice['currency'] ?> <?= number_format($invoice['amount'], 2) ?></p>
                    <p><strong>Estado:</strong> 
                        <span class="badge bg-<?= $invoice['status'] === 'paid' ? 'success' : ($invoice['status'] === 'pending' ? 'warning' : 'danger') ?>">
                            <?= $invoice['status'] === 'paid' ? 'Pagada' : ($invoice['status'] === 'pending' ? 'Pendiente' : 'Cancelada') ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-list me-1"></i>
                Cuotas
            </div>
            <div>
                <?php if (empty($instalments)) : ?>
                    <a href="<?= site_url('invoice/' . $invoice['id'] . '/instalments/create') ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Crear Cuotas
                    </a>
                <?php else : ?>
                    <form action="<?= site_url('invoice/' . $invoice['id'] . '/instalments/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar todas las cuotas? Esta acción no se puede deshacer.')">
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash"></i> Eliminar Cuotas
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($instalments)) : ?>
                <div class="alert alert-info">
                    No hay cuotas registradas para esta factura. 
                    <a href="<?= site_url('invoice/' . $invoice['id'] . '/instalments/create') ?>">Crear cuotas</a>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>N° Cuota</th>
                                <th>Monto</th>
                                <th>Fecha Vencimiento</th>
                                <th>Estado</th>
                                <th>Pagos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($instalments as $instalment) : ?>
                                <?php 
                                    // Calcular clase de fila según estado de la cuota
                                    $rowClass = '';
                                    if ($instalment['is_overdue']) {
                                        $rowClass = 'table-danger'; // Cuota vencida
                                    } elseif ($instalment['can_be_paid'] && $instalment['status'] !== 'paid') {
                                        $rowClass = 'table-warning'; // Cuota que se puede pagar
                                    } elseif ($instalment['is_future']) {
                                        $rowClass = 'table-secondary'; // Cuota futura (no se puede pagar aún)
                                    } elseif ($instalment['status'] === 'paid') {
                                        $rowClass = 'table-success'; // Cuota pagada
                                    }
                                ?>
                                <tr class="<?= $rowClass ?>">
                                    <td><?= $instalment['number'] ?></td>
                                    <td><?= $invoice['currency'] === 'PEN' ? 'S/ ' : '$ ' ?><?= number_format($instalment['amount'], 2) ?></td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($instalment['due_date'])) ?>
                                        <?php if ($instalment['is_overdue']): ?>
                                            <span class="badge bg-danger">Vencida</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $instalment['status'] === 'paid' ? 'success' : ($instalment['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                            <?= $instalment['status'] === 'paid' ? 'Pagada' : ($instalment['status'] === 'pending' ? 'Pendiente' : ucfirst($instalment['status'])) ?>
                                        </span>
                                        <?php if ($instalment['is_future'] && $instalment['status'] !== 'paid'): ?>
                                            <span class="badge bg-info">Pago futuro</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($payments[$instalment['id']])) : ?>
                                            <ul class="list-unstyled mb-0">
                                                <?php foreach ($payments[$instalment['id']] as $payment) : ?>
                                                    <li>
                                                        <small>
                                                            <?= date('d/m/Y', strtotime($payment['payment_date'])) ?> - 
                                                            <?= $invoice['currency'] === 'PEN' ? 'S/ ' : '$ ' ?><?= number_format($payment['amount'], 2) ?>
                                                        </small>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else : ?>
                                            <span class="text-muted">Sin pagos</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($instalment['status'] !== 'paid') : ?>
                                            <?php if ($instalment['can_be_paid']) : ?>
                                                <a href="<?= site_url('payments/create/' . $invoice['uuid'] . '?instalment_id=' . $instalment['id']) ?>" class="btn btn-success btn-sm">
                                                    <i class="fas fa-money-bill"></i> Registrar Pago
                                                </a>
                                            <?php else : ?>
                                                <button class="btn btn-secondary btn-sm" disabled title="No se puede pagar esta cuota hasta que se paguen las anteriores">
                                                    <i class="fas fa-money-bill"></i> Registrar Pago
                                                </button>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="badge bg-success">Completada</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4">
                    <h5>Leyenda:</h5>
                    <div class="d-flex flex-wrap gap-3">
                        <div>
                            <span class="badge bg-danger">Vencida</span> - Cuota pendiente con fecha de vencimiento pasada
                        </div>
                        <div>
                            <span class="badge bg-warning">Pendiente</span> - Cuota por vencer que puede ser pagada
                        </div>
                        <div>
                            <span class="badge bg-info">Pago futuro</span> - Cuota que no puede ser pagada hasta que se paguen las anteriores
                        </div>
                        <div>
                            <span class="badge bg-success">Pagada</span> - Cuota completamente pagada
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function() {
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
</script>
<?= $this->endSection() ?>
