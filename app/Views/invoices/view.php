<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Factura <?= esc($invoice['invoice_number']) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Factura <?= esc($invoice['invoice_number']) ?></h1>
    </div>
    <div class="col text-end">
        <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
            <a href="<?= site_url('invoices/edit/' . $invoice['uuid']) ?>" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <?php if ($invoice['status'] === 'pending'): ?>
                <a href="<?= site_url('payments/create/' . $invoice['uuid']) ?>" class="btn btn-success">
                    <i class="bi bi-cash"></i> Registrar Pago
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
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Detalles de la Factura</h5>
                <hr>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Número de Factura:</strong>
                    </div>
                    <div class="col-md-8">
                        <?= esc($invoice['invoice_number']) ?>
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
                        <?= $invoice['currency'] === 'PEN' ? 'S/ ' : '$ ' ?><?= number_format($invoice['amount'], 2) ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Fecha de Vencimiento:</strong>
                    </div>
                    <div class="col-md-8">
                        <?= date('d/m/Y', strtotime($invoice['due_date'])) ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Estado:</strong>
                    </div>
                    <div class="col-md-8">
                        <span class="badge bg-<?= $invoice['status'] === 'paid' ? 'success' : 
                            ($invoice['status'] === 'pending' ? 'warning' : 
                            ($invoice['status'] === 'cancelled' ? 'danger' : 
                            ($invoice['status'] === 'expired' ? 'secondary' : 'info'))) ?>">
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
    </div>

    <div class="col-md-4">
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

        <?php if ($invoice['status'] === 'pending'): ?>
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Acciones</h5>
                <hr>
                
                <div class="d-grid gap-2">
                    <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
                        <a href="<?= site_url('payments/create/' . $invoice['uuid']) ?>" class="btn btn-success">
                            <i class="bi bi-cash"></i> Registrar Pago
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>