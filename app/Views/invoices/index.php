<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Cuentas por Cobrar<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-md-6">
        <h1>Cuentas por Cobrar</h1>
    </div>
    <div class="col-md-6 text-end">
        <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
            <a href="<?= site_url('invoices/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus"></i> Nueva Cuenta
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
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
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
        No se encontraron cuentas por cobrar con los filtros aplicados.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Nro. Factura</th>
                    <th>Cliente</th>
                    <th>RUC/CI</th>
                    <?php if (isset($organizations) && $auth->hasRole('superadmin') && !isset($selected_organization_id)): ?>
                    <th>Organización</th>
                    <?php endif; ?>
                    <th>Concepto</th>
                    <th>Monto</th>
                    <th>Pago</th>
                    <th>Fecha Vencimiento</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?= esc($invoice['invoice_number']) ?></td>
                        <td><?= esc($invoice['client_name']) ?></td>
                        <td><?= esc($invoice['document_number']) ?></td>
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
                        <td><?= esc($invoice['concept']) ?></td>
                        <td>$<?= number_format($invoice['amount'], 2) ?></td>
                        <td>
                            <?php if ($invoice['status'] === 'pending' && isset($invoice['has_partial_payment']) && $invoice['has_partial_payment']): ?>
                                <div class="d-flex flex-column">
                                    <small>Pagado: $<?= number_format($invoice['total_paid'], 2) ?></small>
                                    <small>Pendiente: $<?= number_format($invoice['remaining_amount'], 2) ?></small>
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?= round($invoice['payment_percentage']) ?>%;" 
                                             aria-valuenow="<?= round($invoice['payment_percentage']) ?>" 
                                             aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            <?php elseif ($invoice['status'] === 'paid'): ?>
                                <span class="badge bg-success">Pagado</span>
                            <?php elseif ($invoice['status'] === 'pending'): ?>
                                <span class="badge bg-warning text-dark">Sin pagos</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">N/A</span>
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
                                    $statusText = 'Cancelada';
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
                                        onclick="confirmDelete(<?= $invoice['id'] ?>, '<?= esc($invoice['invoice_number']) ?>')">
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
                ¿Está seguro que desea eliminar la cuenta por cobrar <span id="invoiceNumber"></span>?
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