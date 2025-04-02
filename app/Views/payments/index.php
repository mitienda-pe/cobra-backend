<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Pagos<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-md-6">
        <h1>Pagos</h1>
    </div>
    <div class="col-md-6 text-end">
        <!-- <a href="<?= site_url('payments/create') ?>" class="btn btn-success">
            <i class="bi bi-plus"></i> Registrar Pago
        </a> -->
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= site_url('payments') ?>" class="row g-3">
            <div class="col-md-4">
                <label for="date_start" class="form-label">Fecha Desde</label>
                <input type="date" class="form-control" id="date_start" name="date_start" value="<?= $date_start ?>">
            </div>
            <div class="col-md-4">
                <label for="date_end" class="form-label">Fecha Hasta</label>
                <input type="date" class="form-control" id="date_end" name="date_end" value="<?= $date_end ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="<?= site_url('payments') ?>" class="btn btn-outline-secondary ms-2">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($payments)): ?>
    <div class="alert alert-info">
        No se encontraron pagos con los filtros aplicados.
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Factura</th>
                            <th>Monto</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th>Estado</th>
                            <th>Notificación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($payment['payment_date'])) ?></td>
                                <td><?= esc($payment['business_name'] ?? 'N/A') ?></td>
                                <td><?= esc($payment['number'] ?? $payment['invoice_number'] ?? 'N/A') ?></td>
                                <td><?= $payment['currency'] === 'PEN' ? 'S/ ' : '$ ' ?><?= number_format($payment['amount'], 2) ?></td>
                                <td><?= esc($payment['payment_method']) ?></td>
                                <td><?= esc($payment['reference_code']) ?></td>
                                <td>
                                    <?php if ($payment['status'] === 'completed'): ?>
                                        <span class="badge bg-success">Completado</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($payment['is_notified']): ?>
                                        <span class="badge bg-success">Enviada</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <div class="btn-group" role="group">
                                        <a href="<?= site_url('payments/view/' . $payment['uuid']) ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>

                                        <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete('<?= $payment['uuid'] ?>')">
                                                <i class="bi bi-trash"></i> Eliminar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?= $pager->links() ?>
        </div>
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

<?= $this->section('scripts') ?>
<script>
    function confirmDelete(uuid) {
        document.getElementById('deleteLink').href = '<?= site_url('payments/delete/') ?>' + uuid;
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>