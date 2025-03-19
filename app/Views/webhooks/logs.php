<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Logs de Webhook<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Logs de Webhook: <?= esc($webhook['name']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= site_url('webhooks') ?>">Webhooks</a></li>
                <li class="breadcrumb-item active">Logs</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Información del Webhook</span>
                <div>
                    <a href="<?= site_url('webhooks/test/' . $webhook['id']) ?>" class="btn btn-sm btn-warning">
                        Enviar Evento de Prueba
                    </a>
                    <a href="<?= site_url('webhooks/edit/' . $webhook['id']) ?>" class="btn btn-sm btn-primary">
                        Editar Webhook
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">URL:</dt>
                            <dd class="col-sm-8"><?= esc($webhook['url']) ?></dd>
                            
                            <dt class="col-sm-4">Estado:</dt>
                            <dd class="col-sm-8">
                                <?php if ($webhook['is_active']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactivo</span>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Eventos:</dt>
                            <dd class="col-sm-8">
                                <?php 
                                $events = explode(',', $webhook['events']);
                                foreach ($events as $event) {
                                    echo "<span class='badge bg-secondary me-1'>{$event}</span>";
                                }
                                ?>
                            </dd>
                            
                            <dt class="col-sm-4">Creado:</dt>
                            <dd class="col-sm-8"><?= date('d/m/Y H:i:s', strtotime($webhook['created_at'])) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($logs)): ?>
    <div class="alert alert-info">
        No hay registros de eventos enviados a este webhook. Utilice el botón "Enviar Evento de Prueba" para probar la configuración.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Evento</th>
                    <th>Código de Estado</th>
                    <th>Intentos</th>
                    <th>Respuesta</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                        <td><?= esc($log['event']) ?></td>
                        <td>
                            <?php if ($log['status_code'] >= 200 && $log['status_code'] < 300): ?>
                                <span class="badge bg-success"><?= $log['status_code'] ?></span>
                            <?php elseif ($log['status_code'] >= 400 && $log['status_code'] < 500): ?>
                                <span class="badge bg-warning text-dark"><?= $log['status_code'] ?></span>
                            <?php elseif ($log['status_code'] >= 500): ?>
                                <span class="badge bg-danger"><?= $log['status_code'] ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= $log['status_code'] ?? 'N/A' ?></span>
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
                                    onclick="showResponseModal('<?= esc(json_encode($log['response']), 'attr') ?>', '<?= esc(json_encode($log['payload']), 'attr') ?>')">
                                Ver Detalles
                            </button>
                        </td>
                        <td>
                            <?php if (($log['status_code'] >= 400 || $log['status_code'] === null) && ($log['attempts'] < 3)): ?>
                                <a href="<?= site_url('webhooks/retry/' . $log['id']) ?>" class="btn btn-sm btn-warning">
                                    Reintentar
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal para ver la respuesta -->
<div class="modal fade" id="responseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles de la Notificación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6>Payload Enviado:</h6>
                    <pre id="payloadContent" class="bg-light p-3 overflow-auto" style="max-height: 200px;"></pre>
                </div>
                <div>
                    <h6>Respuesta Recibida:</h6>
                    <pre id="responseContent" class="bg-light p-3 overflow-auto" style="max-height: 200px;"></pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    function showResponseModal(response, payload) {
        try {
            // Procesar respuesta
            let responseObj = JSON.parse(response);
            document.getElementById('responseContent').textContent = JSON.stringify(responseObj, null, 2);
        } catch (e) {
            // Si no es JSON, mostrar como texto
            document.getElementById('responseContent').textContent = response || 'No hay respuesta disponible';
        }
        
        try {
            // Procesar payload
            let payloadObj = JSON.parse(payload);
            document.getElementById('payloadContent').textContent = JSON.stringify(payloadObj, null, 2);
        } catch (e) {
            // Si no es JSON, mostrar como texto
            document.getElementById('payloadContent').textContent = payload || 'No hay datos disponibles';
        }
        
        // Mostrar modal
        var modal = new bootstrap.Modal(document.getElementById('responseModal'));
        modal.show();
    }
</script>
<?= $this->endSection() ?>