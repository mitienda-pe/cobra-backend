<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Detalles de Pago<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Detalles de Pago #<?= esc($payment['id']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= site_url('payments') ?>">Pagos</a></li>
                <li class="breadcrumb-item active">Detalles</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <!-- Cliente Card -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-person-circle"></i> Información del Cliente
            </div>
            <div class="card-body">
                <h6 class="card-title"><?= esc($client['business_name']) ?></h6>
                <div class="mb-2">
                    <small class="text-muted">Documento:</small><br>
                    <strong><?= esc($client['document_number']) ?></strong>
                </div>
                <?php if (!empty($client['email'])): ?>
                    <div class="mb-2">
                        <small class="text-muted">Email:</small><br>
                        <span><?= esc($client['email']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($client['phone'])): ?>
                    <div class="mb-2">
                        <small class="text-muted">Teléfono:</small><br>
                        <span><?= esc($client['phone']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Factura Card -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <i class="bi bi-file-text"></i> Información de la Factura
            </div>
            <div class="card-body">
                <h6 class="card-title"><?= esc($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A') ?></h6>
                <div class="mb-2">
                    <small class="text-muted">Concepto:</small><br>
                    <span><?= esc($invoice['concept']) ?></span>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Monto Total:</small><br>
                    <strong class="fs-5">S/ <?= number_format($invoice['amount'], 2) ?></strong>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Fecha de Emisión:</small><br>
                    <span><?= date('d/m/Y', strtotime($invoice['created_at'])) ?></span>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Fecha de Vencimiento:</small><br>
                    <strong><?= date('d/m/Y', strtotime($invoice['due_date'])) ?></strong>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Estado:</small><br>
                    <?php
                    $statusClass = '';
                    $statusText = '';

                    switch ($invoice['status']) {
                        case 'pending':
                            $statusClass = 'bg-warning text-dark';
                            $statusText = 'Pendiente';
                            break;
                        case 'paid':
                            $statusClass = 'bg-success';
                            $statusText = 'Pagada';
                            break;
                        case 'cancelled':
                            $statusClass = 'bg-danger';
                            $statusText = 'Cancelada';
                            break;
                        case 'rejected':
                            $statusClass = 'bg-secondary';
                            $statusText = 'Rechazada';
                            break;
                    }
                    ?>
                    <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                </div>
            </div>
            <div class="card-footer">
                <a href="<?= site_url('invoices/view/' . $invoice['uuid']) ?>" class="btn btn-info btn-sm">
                    <i class="bi bi-eye"></i> Ver Factura
                </a>
            </div>
        </div>
    </div>

    <!-- Cuota Card (si aplica) -->
    <?php if ($instalment): ?>
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-calendar-event"></i> Información de la Cuota
            </div>
            <div class="card-body">
                <h6 class="card-title">
                    Cuota <?= $instalment['number'] ?>/<?= count($allInstalments) ?>
                </h6>
                <div class="mb-2">
                    <small class="text-muted">Monto de la Cuota:</small><br>
                    <strong class="fs-5">S/ <?= number_format($instalment['amount'], 2) ?></strong>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Fecha de Vencimiento:</small><br>
                    <strong><?= date('d/m/Y', strtotime($instalment['due_date'])) ?></strong>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Estado de la Cuota:</small><br>
                    <?php if ($instalment['status'] === 'paid'): ?>
                        <span class="badge bg-success">Pagada</span>
                    <?php elseif ($instalment['status'] === 'pending'): ?>
                        <span class="badge bg-warning text-dark">Pendiente</span>
                    <?php else: ?>
                        <span class="badge bg-secondary"><?= ucfirst($instalment['status']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($nextInstalment): ?>
                    <div class="mb-2">
                        <small class="text-muted">Próxima Cuota:</small><br>
                        <strong>Cuota <?= $nextInstalment['number'] ?></strong><br>
                        <small>Vence: <?= date('d/m/Y', strtotime($nextInstalment['due_date'])) ?></small><br>
                        <small>Monto: S/ <?= number_format($nextInstalment['amount'], 2) ?></small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pago Card -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <i class="bi bi-credit-card"></i> Información del Pago
            </div>
            <div class="card-body">
                <h6 class="card-title">Pago #<?= $payment['id'] ?></h6>
                <div class="mb-2">
                    <small class="text-muted">Monto Pagado:</small><br>
                    <strong class="fs-4 text-success">S/ <?= number_format($payment['amount'], 2) ?></strong>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Fecha de Pago:</small><br>
                    <strong><?= date('d/m/Y H:i:s', strtotime($payment['payment_date'])) ?></strong>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Método de Pago:</small><br>
                    <?php 
                    $methodName = $payment['payment_method'];
                    switch($payment['payment_method']) {
                        case 'qr': $methodName = 'QR (Yape/Plin)'; break;
                        case 'cash': $methodName = 'Efectivo'; break;
                        case 'transfer': $methodName = 'Transferencia'; break;
                    }
                    ?>
                    <span class="badge bg-info"><?= esc($methodName) ?></span>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Estado:</small><br>
                    <?php if ($payment['status'] === 'completed'): ?>
                        <span class="badge bg-success">Completado</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Pendiente</span>
                    <?php endif; ?>
                </div>
                <?php if ($payment['reference_code']): ?>
                    <div class="mb-2">
                        <small class="text-muted">Código de Referencia:</small><br>
                        <code><?= esc($payment['reference_code']) ?></code>
                    </div>
                <?php endif; ?>
                <div class="mb-2">
                    <small class="text-muted">Cobrador:</small><br>
                    <span><?= esc($collector['name'] ?? 'N/A') ?></span>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Notificación:</small><br>
                    <?php if ($payment['is_notified']): ?>
                        <span class="badge bg-success">Enviada</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Pendiente</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sección de Notas (solo para superadmin) -->
<?php if ($payment['notes'] && $auth->hasRole('superadmin')): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-shield-check"></i> Notas Técnicas (Solo Superadmin)
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Notas del Sistema:</label>
                    <pre class="bg-light p-3 rounded"><code><?= esc($payment['notes']) ?></code></pre>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Mapa de Ubicación (si disponible) -->
<?php if ($payment['latitude'] && $payment['longitude']): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <i class="bi bi-geo-alt"></i> Ubicación del Pago
            </div>
            <div class="card-body">
                <div id="map" style="height: 400px; width: 100%;"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- QR Code (si aplica) -->
<?php if (!empty($show_ligo_qr)): ?>
<div class="row mb-4">
    <div class="col-md-6 mx-auto">
        <div class="card border-success">
            <div class="card-header bg-success text-white text-center">
                <i class="bi bi-qr-code"></i> Código QR para Pago
            </div>
            <div class="card-body text-center">
                <p class="fw-bold">Presenta este código QR para completar el pago:</p>
                <?php if (!empty($qr_url)): ?>
                    <img src="<?= esc($qr_url) ?>" alt="QR" class="img-fluid mb-2" style="max-width: 250px;" />
                <?php endif; ?>
                <?php if (!empty($qr_hash['hash'])): ?>
                    <div class="mt-2 small text-muted">
                        Hash QR: <code><?= esc($qr_hash['hash']) ?></code>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Botones de Acción -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="<?= site_url('invoices/view/' . $invoice['uuid']) ?>" class="btn btn-info">
                        <i class="bi bi-file-text"></i> Ver Factura Completa
                    </a>
                    
                    <?php if ($instalment && $nextInstalment): ?>
                        <a href="<?= site_url('payments/create?invoice=' . $invoice['uuid'] . '&instalment=' . $nextInstalment['id']) ?>" class="btn btn-warning">
                            <i class="bi bi-credit-card"></i> Pagar Próxima Cuota
                        </a>
                    <?php endif; ?>

                    <?php if (false && $auth->hasAnyRole(['superadmin', 'admin'])): ?>
                        <button type="button" class="btn btn-danger" onclick="confirmDelete('<?= $payment['uuid'] ?>')">
                            <i class="bi bi-trash"></i> Eliminar Pago
                        </button>
                    <?php endif; ?>

                    <a href="<?= site_url('payments') ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Volver a Pagos
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmación de Eliminación -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ¿Está seguro que desea eliminar este pago?
                <br><br>
                <strong>Esta acción no se puede deshacer y podría cambiar el estado de la factura.</strong>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="deleteLink" class="btn btn-danger">Eliminar</a>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(uuid) {
        document.getElementById('deleteLink').href = '<?= site_url('payments/delete/') ?>' + uuid;

        var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }

    <?php if ($payment['latitude'] && $payment['longitude']): ?>
        // Inicializar el mapa
        document.addEventListener('DOMContentLoaded', function() {
            // Cargar el script de Google Maps
            var script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initMap';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        });

        function initMap() {
            const paymentLocation = {
                lat: <?= $payment['latitude'] ?>,
                lng: <?= $payment['longitude'] ?>
            };

            const map = new google.maps.Map(document.getElementById("map"), {
                zoom: 15,
                center: paymentLocation,
            });

            const marker = new google.maps.Marker({
                position: paymentLocation,
                map: map,
                title: "Ubicación del pago",
            });
        }
    <?php endif; ?>
</script>
<?= $this->endSection() ?>