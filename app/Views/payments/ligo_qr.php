<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= $title ?? 'Pago con QR - Ligo' ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Pago con QR - Ligo</h4>
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title">Factura #<?= $invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A' ?></h5>
                    <p class="card-text">Monto a pagar: <?= $invoice['currency'] ?? 'PEN' ?> <?= number_format($invoice['total_amount'] ?? $invoice['amount'] ?? 0, 2) ?></p>
                    
                    <?php if (isset($error_message) && !empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <h5><i class="bi bi-exclamation-triangle-fill"></i> Error</h5>
                            <p><?= $error_message ?></p>
                            <p class="mb-0 small">Si el problema persiste, por favor contacte al administrador del sistema.</p>
                        </div>
                    <?php elseif (isset($qr_image_url) && !empty($qr_image_url)): ?>
                        <div class="my-4">
                            <img src="<?= $qr_image_url ?>" alt="QR Code" class="img-fluid" style="max-width: 250px;">
                        </div>
                        <p class="text-muted">Escanea el código QR con tu aplicación de Ligo para realizar el pago</p>
                        
                        <?php if (isset($is_demo) && $is_demo): ?>
                            <div class="alert alert-warning">
                                <p><strong>Modo Demostración</strong></p>
                                <p class="mb-0">Este es un QR de demostración. Para habilitar pagos reales con Ligo, configure las credenciales en la sección de configuración de la organización.</p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($expiration) && !empty($expiration)): ?>
                            <p class="text-muted">
                                <small>Este código QR expira el: <?= date('d/m/Y H:i', strtotime($expiration)) ?></small>
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            No se pudo generar el código QR. Por favor, intente nuevamente.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-6">
                            <a href="<?= site_url('invoices/view/' . $invoice['id']) ?>" class="btn btn-secondary btn-block">
                                <i class="bi bi-arrow-left"></i> Volver
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="<?= site_url('payment/ligo/qr/' . $invoice['id']) ?>" class="btn btn-primary btn-block">
                                <i class="bi bi-arrow-repeat"></i> Regenerar QR
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
