<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Webhooks<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-md-6">
        <h1>Webhooks</h1>
    </div>
    <div class="col-md-6 text-end">
        <a href="<?= site_url('webhooks/ligo-logs') ?>" class="btn btn-info me-2">
            <i class="bi bi-receipt"></i> Notificaciones de Ligo
        </a>
        <a href="<?= site_url('webhooks/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus"></i> Nuevo Webhook
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-receipt"></i> Notificaciones de Ligo
                </h5>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <strong>Monitoreo de Pagos:</strong> Vea las notificaciones de confirmación de pagos que llegan cuando se completan transacciones.
                </p>
                <p class="mb-0">
                    <strong>Endpoint:</strong> <code>/api/webhooks/ligo</code> - Recibe automáticamente notificaciones de pagos procesados.
                </p>
            </div>
        </div>
    </div>
</div>

<?php if (empty($webhooks)): ?>
    <div class="alert alert-info">
        No se encontraron webhooks configurados. Haga clic en "Nuevo Webhook" para crear uno.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>URL</th>
                    <th>Eventos</th>
                    <th>Estado</th>
                    <th>Creado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($webhooks as $webhook): ?>
                    <tr>
                        <td><?= esc($webhook['name']) ?></td>
                        <td class="text-truncate" style="max-width: 250px;"><?= esc($webhook['url']) ?></td>
                        <td>
                            <?php
                            $events = explode(',', $webhook['events']);
                            foreach ($events as $event) {
                                $badgeClass = 'bg-secondary';
                                switch ($event) {
                                    case 'payment.created':
                                    case 'payment.updated':
                                        $badgeClass = 'bg-success';
                                        break;
                                    case 'invoice.created':
                                    case 'invoice.updated':
                                        $badgeClass = 'bg-primary';
                                        break;
                                    case 'invoice.paid':
                                        $badgeClass = 'bg-info';
                                        break;
                                }
                                echo "<span class='badge {$badgeClass} me-1'>{$event}</span>";
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($webhook['is_active']): ?>
                                <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($webhook['created_at'])) ?></td>
                        <td class="text-nowrap">
                            <a href="<?= site_url('webhooks/edit/' . $webhook['id']) ?>" class="btn btn-sm btn-primary">
                                Editar
                            </a>
                            <a href="<?= site_url('webhooks/logs/' . $webhook['id']) ?>" class="btn btn-sm btn-info">
                                Logs
                            </a>
                            <a href="<?= site_url('webhooks/test/' . $webhook['id']) ?>" class="btn btn-sm btn-warning">
                                Probar
                            </a>
                            <button type="button" class="btn btn-sm btn-danger"
                                onclick="confirmDelete(<?= $webhook['id'] ?>, '<?= esc($webhook['name']) ?>')">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal de Confirmación de Eliminación -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ¿Está seguro que desea eliminar el webhook <span id="webhookName"></span>?
                <br><br>
                <strong>Esta acción eliminará también todos los logs asociados y no se puede deshacer.</strong>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="deleteLink" class="btn btn-danger">Eliminar</a>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id, name) {
        document.getElementById('webhookName').textContent = name;
        document.getElementById('deleteLink').href = '<?= site_url('webhooks/delete/') ?>' + id;

        var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }
</script>
<?= $this->endSection() ?>