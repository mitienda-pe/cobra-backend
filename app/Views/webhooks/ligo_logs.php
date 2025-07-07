<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Notificaciones de Ligo<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Notificaciones de Ligo</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= site_url('webhooks') ?>">Webhooks</a></li>
                <li class="breadcrumb-item active">Notificaciones de Ligo</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Información del Webhook de Ligo</span>
                <div>
                    <button type="button" class="btn btn-sm btn-info" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Actualizar
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Endpoint:</dt>
                            <dd class="col-sm-8">
                                <code>/api/webhooks/ligo</code>
                            </dd>
                            
                            <dt class="col-sm-4">Estado:</dt>
                            <dd class="col-sm-8">
                                <?php if ($webhook && $webhook['is_active']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Inicializando...</span>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Eventos:</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-secondary me-1">payment.succeeded</span>
                                <span class="badge bg-secondary me-1">payment.failed</span>
                                <span class="badge bg-secondary me-1">payment.cancelled</span>
                            </dd>
                            
                            <dt class="col-sm-4">Total Logs:</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-info"><?= count($logs) ?></span>
                            </dd>
                        </dl>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Acerca de este webhook:</strong> Este endpoint recibe notificaciones de confirmación de pagos desde Ligo. 
                            Cada vez que un pago es procesado exitosamente, Ligo envía una notificación a este endpoint para actualizar 
                            el estado de las cuotas de pendiente a pagado.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($logs)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>No hay notificaciones registradas.</strong> 
        Este webhook aún no ha recibido notificaciones de Ligo. Las notificaciones aparecerán aquí cuando:
        <ul class="mt-2 mb-0">
            <li>Se complete un pago a través de códigos QR de Ligo</li>
            <li>Se confirme el pago de una cuota</li>
            <li>Se actualice el estado de una factura</li>
        </ul>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Fecha/Hora</th>
                    <th>Evento</th>
                    <th>Estado</th>
                    <th>Código de Respuesta</th>
                    <th>Procesado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                            <br>
                            <small class="text-muted">
                                <?= date('D', strtotime($log['created_at'])) ?>
                            </small>
                        </td>
                        <td>
                            <?php 
                            $eventClass = '';
                            switch ($log['event']) {
                                case 'payment.succeeded':
                                    $eventClass = 'bg-success';
                                    $eventText = 'Pago Exitoso';
                                    break;
                                case 'payment.failed':
                                    $eventClass = 'bg-danger';
                                    $eventText = 'Pago Fallido';
                                    break;
                                case 'payment.cancelled':
                                    $eventClass = 'bg-warning text-dark';
                                    $eventText = 'Pago Cancelado';
                                    break;
                                default:
                                    $eventClass = 'bg-secondary';
                                    $eventText = esc($log['event']);
                            }
                            ?>
                            <span class="badge <?= $eventClass ?>"><?= $eventText ?></span>
                        </td>
                        <td>
                            <?php if ($log['success']): ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle"></i> Exitoso
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger">
                                    <i class="bi bi-x-circle"></i> Error
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['response_code'] >= 200 && $log['response_code'] < 300): ?>
                                <span class="badge bg-success"><?= $log['response_code'] ?></span>
                            <?php elseif ($log['response_code'] >= 400 && $log['response_code'] < 500): ?>
                                <span class="badge bg-warning text-dark"><?= $log['response_code'] ?></span>
                            <?php elseif ($log['response_code'] >= 500): ?>
                                <span class="badge bg-danger"><?= $log['response_code'] ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= $log['response_code'] ?? 'N/A' ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $log['attempts'] ?>
                            <?php if ($log['attempts'] > 1): ?>
                                <span class="badge bg-info ms-1" title="Reintentado">
                                    <i class="bi bi-arrow-repeat"></i>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                    onclick="showLigoNotificationModal('<?= esc(json_encode($log['response_body']), 'attr') ?>', '<?= esc(json_encode($log['payload']), 'attr') ?>')">
                                <i class="bi bi-eye"></i> Ver Detalles
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="mt-3">
        <small class="text-muted">
            <i class="bi bi-info-circle"></i>
            Mostrando <?= count($logs) ?> notificaciones más recientes.
        </small>
    </div>
<?php endif; ?>

<!-- Modal para ver detalles de la notificación -->
<div class="modal fade" id="ligoNotificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-receipt"></i> Detalles de la Notificación de Ligo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6><i class="bi bi-arrow-down-circle"></i> Payload Recibido desde Ligo:</h6>
                    <pre id="ligoPayloadContent" class="bg-light p-3 overflow-auto border rounded" style="max-height: 300px;"></pre>
                </div>
                <div>
                    <h6><i class="bi bi-arrow-up-circle"></i> Respuesta Enviada:</h6>
                    <pre id="ligoResponseContent" class="bg-light p-3 overflow-auto border rounded" style="max-height: 200px;"></pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    function showLigoNotificationModal(response, payload) {
        try {
            // Procesar respuesta
            let responseObj = JSON.parse(response);
            document.getElementById('ligoResponseContent').textContent = JSON.stringify(responseObj, null, 2);
        } catch (e) {
            // Si no es JSON, mostrar como texto
            document.getElementById('ligoResponseContent').textContent = response || 'No hay respuesta disponible';
        }
        
        try {
            // Procesar payload
            let payloadObj = JSON.parse(payload);
            document.getElementById('ligoPayloadContent').textContent = JSON.stringify(payloadObj, null, 2);
        } catch (e) {
            // Si no es JSON, mostrar como texto
            document.getElementById('ligoPayloadContent').textContent = payload || 'No hay datos disponibles';
        }
        
        // Mostrar modal
        var modal = new bootstrap.Modal(document.getElementById('ligoNotificationModal'));
        modal.show();
    }
</script>
<?= $this->endSection() ?>