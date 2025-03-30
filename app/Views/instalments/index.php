<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Cuotas de la Factura #<?= $invoice['invoice_number'] ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid px-4">
    <h1 class="mt-4">Cuotas de la Factura #<?= $invoice['invoice_number'] ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('invoices') ?>">Facturas</a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('invoices/view/' . $invoice['uuid']) ?>">Factura #<?= $invoice['invoice_number'] ?></a></li>
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
                                <tr>
                                    <td><?= $instalment['number'] ?></td>
                                    <td><?= $invoice['currency'] ?> <?= number_format($instalment['amount'], 2) ?></td>
                                    <td><?= date('d/m/Y', strtotime($instalment['due_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $instalment['status'] === 'paid' ? 'success' : ($instalment['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                            <?= $instalment['status'] === 'paid' ? 'Pagada' : ($instalment['status'] === 'pending' ? 'Pendiente' : 'Cancelada') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $instalmentPayments = isset($payments[$instalment['id']]) ? $payments[$instalment['id']] : [];
                                        $totalPaid = 0;
                                        foreach ($instalmentPayments as $payment) {
                                            $totalPaid += $payment['amount'];
                                        }
                                        ?>
                                        <?php if (!empty($instalmentPayments)) : ?>
                                            <span data-bs-toggle="tooltip" data-bs-placement="top" title="<?= count($instalmentPayments) ?> pagos registrados">
                                                <?= $invoice['currency'] ?> <?= number_format($totalPaid, 2) ?>
                                            </span>
                                        <?php else : ?>
                                            <span class="text-muted">Sin pagos</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= site_url('payments/create/' . $invoice['uuid']) ?>?instalment_id=<?= $instalment['id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-money-bill"></i> Registrar Pago
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
