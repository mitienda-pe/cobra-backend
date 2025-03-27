<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Facturas<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-md-6">
        <h1>Facturas</h1>
    </div>
    <div class="col-md-6 text-end">
        <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
            <a href="<?= site_url('invoices/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus"></i> Nueva Factura
            </a>
            <a href="<?= site_url('invoices/import') ?>" class="btn btn-outline-primary">
                <i class="bi bi-upload"></i> Importar
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= site_url('invoices') ?>" class="row g-3">
            <div class="col-md-4">
                <label for="status" class="form-label">Estado</label>
                <select name="status" id="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Pagada</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Anulada</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rechazada</option>
                </select>
            </div>
            
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="<?= site_url('invoices') ?>" class="btn btn-outline-secondary ms-2">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($invoices)): ?>
    <div class="alert alert-info">
        No se encontraron facturas con los filtros aplicados.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Serie-Número</th>
                    <th>Cliente</th>
                    <th>Documento</th>
                    <?php if (isset($organizations) && $auth->hasRole('superadmin') && !isset($selected_organization_id)): ?>
                    <th>Organización</th>
                    <?php endif; ?>
                    <th>F. Emisión</th>
                    <th>Moneda</th>
                    <th>Total</th>
                    <th>Pagado</th>
                    <th>F. Vencimiento</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?= esc($invoice['series'] . '-' . $invoice['number']) ?></td>
                        <td><?= esc($invoice['client_name']) ?></td>
                        <td><?= esc($invoice['client_document_type'] . ': ' . $invoice['client_document_number']) ?></td>
                        <?php if (isset($organizations) && $auth->hasRole('superadmin') && !isset($selected_organization_id)): ?>
                        <td>
                            <?php 
                                // Find organization name
                                $orgName = 'Desconocida';
                                foreach ($organizations as $org) {
                                    if ($org['id'] == $invoice['organization_id']) {
                                        $orgName = $org['name'];
                                        break;
                                    }
                                }
                                echo esc($orgName);
                            ?>
                        </td>
                        <?php endif; ?>
                        <td><?= esc($invoice['issue_date']) ?></td>
                        <td><?= esc($invoice['currency']) ?></td>
                        <td><?= $invoice['currency'] ?> <?= number_format($invoice['total_amount'], 2) ?></td>
                        <td>
                            <?php if ($invoice['status'] === 'pending' && $invoice['paid_amount'] > 0): ?>
                                <div class="d-flex flex-column">
                                    <small>Pagado: <?= $invoice['currency'] ?> <?= number_format($invoice['paid_amount'], 2) ?></small>
                                    <small>Pendiente: <?= $invoice['currency'] ?> <?= number_format($invoice['total_amount'] - $invoice['paid_amount'], 2) ?></small>
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?= round(($invoice['paid_amount'] / $invoice['total_amount']) * 100) ?>%;" 
                                             aria-valuenow="<?= round(($invoice['paid_amount'] / $invoice['total_amount']) * 100) ?>" 
                                             aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?= $invoice['currency'] ?> <?= number_format($invoice['paid_amount'], 2) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($invoice['due_date'])) ?></td>
                        <td>
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
                                    $statusText = 'Anulada';
                                    break;
                                case 'rejected':
                                    $statusClass = 'bg-secondary';
                                    $statusText = 'Rechazada';
                                    break;
                            }
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                        </td>
                        <td class="text-nowrap">
                            <a href="<?= site_url('invoices/view/' . $invoice['id']) ?>" class="btn btn-sm btn-info">
                                Ver
                            </a>
                            
                            <?php if ($auth->hasAnyRole(['superadmin', 'admin']) && $invoice['status'] !== 'paid'): ?>
                                <a href="<?= site_url('invoices/edit/' . $invoice['id']) ?>" class="btn btn-sm btn-primary">
                                    Editar
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($invoice['status'] === 'pending'): ?>
                                <a href="<?= site_url('payments/create/' . $invoice['id']) ?>" class="btn btn-sm btn-success">
                                    Registrar Pago
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($auth->hasAnyRole(['superadmin', 'admin']) && $invoice['status'] !== 'paid'): ?>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="confirmDelete(<?= $invoice['id'] ?>, '<?= esc($invoice['series'] . '-' . $invoice['number']) ?>')">
                                    Eliminar
                                </button>
                            <?php endif; ?>
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
                ¿Está seguro que desea eliminar la factura <span id="invoiceNumber"></span>?
                <br><br>
                <strong>Esta acción no se puede deshacer.</strong>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="deleteLink" class="btn btn-danger">Eliminar</a>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id, invoiceNumber) {
        document.getElementById('invoiceNumber').textContent = invoiceNumber;
        document.getElementById('deleteLink').href = '<?= site_url('invoices/delete/') ?>' + id;
        
        var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }
</script>
<?= $this->endSection() ?>