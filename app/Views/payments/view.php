<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Detalles de Pago<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Detalles de Pago</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= site_url('payments') ?>">Pagos</a></li>
                <li class="breadcrumb-item active">Detalles</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                Información del Pago
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Factura:</label>
                            <div><?= esc($invoice['invoice_number']) ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Cliente:</label>
                            <div><?= esc($client['business_name']) ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Documento:</label>
                            <div><?= esc($client['document_number']) ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Monto Pagado:</label>
                            <div class="fs-4">$<?= number_format($payment['amount'], 2) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Fecha de Pago:</label>
                            <div><?= date('d/m/Y H:i:s', strtotime($payment['payment_date'])) ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Método de Pago:</label>
                            <div><?= esc($payment['payment_method']) ?></div>
                        </div>
                        <?php if ($payment['reference_code']): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Código de Referencia:</label>
                            <div><?= esc($payment['reference_code']) ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Estado:</label>
                            <div>
                                <?php if ($payment['status'] === 'completed'): ?>
                                    <span class="badge bg-success">Completado</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($payment['notes']): ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">Notas:</label>
                    <div><?= nl2br(esc($payment['notes'])) ?></div>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Cobrador:</label>
                    <div><?= esc($collector['name']) ?> (<?= esc($collector['email']) ?>)</div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Notificación:</label>
                    <div>
                        <?php if ($payment['is_notified']): ?>
                            <span class="badge bg-success">Enviada</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Pendiente</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($payment['latitude'] && $payment['longitude']): ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">Ubicación:</label>
                    <div class="mt-2">
                        <div id="map" style="height: 300px; width: 100%;"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer d-flex gap-2">
                <a href="<?= site_url('invoices/view/' . $invoice['uuid']) ?>" class="btn btn-info">
                    Ver Factura
                </a>
                
                <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete(<?= $payment['id'] ?>)">
                        Eliminar Pago
                    </button>
                <?php endif; ?>
                
                <a href="<?= site_url('payments') ?>" class="btn btn-secondary ms-auto">
                    Volver
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                Información de la Cuenta
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Número de Factura:</label>
                    <div><?= esc($invoice['invoice_number']) ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Concepto:</label>
                    <div><?= esc($invoice['concept']) ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Monto Total:</label>
                    <div>$<?= number_format($invoice['amount'], 2) ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Fecha de Vencimiento:</label>
                    <div><?= date('d/m/Y', strtotime($invoice['due_date'])) ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Estado:</label>
                    <div>
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
    function confirmDelete(id) {
        document.getElementById('deleteLink').href = '<?= site_url('payments/delete/') ?>' + id;
        
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