<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Facturas<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Facturas</h1>
    </div>
    <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
    <div class="col text-end">
        <a href="<?= site_url('invoices/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nueva Factura
        </a>
        <a href="<?= site_url('invoices/import') ?><?= (isset($selected_organization_id) && $selected_organization_id) ? '?organization_id=' . $selected_organization_id : '' ?>" class="btn btn-outline-primary">
            <i class="bi bi-upload"></i> Importar
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($auth->hasRole('superadmin') && !isset($selected_organization_id)): ?>
<div class="row mb-4">
    <div class="col">
        <div class="card">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label for="organization_id" class="col-form-label">Filtrar por Organización:</label>
                    </div>
                    <div class="col-auto">
                        <select name="organization_id" id="organization_id" class="form-select">
                            <option value="">Todas las organizaciones</option>
                            <?php foreach ($organizations as $org): ?>
                                <option value="<?= $org['id'] ?>" <?= $selected_organization_id == $org['id'] ? 'selected' : '' ?>>
                                    <?= esc($org['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Concepto</th>
                        <th>Importe</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><?= esc($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A') ?></td>
                            <td><?= esc($invoice['business_name'] ?? 'N/A') ?></td>
                            <td><?= esc($invoice['concept']) ?></td>
                            <td>S/ <?= number_format($invoice['total_amount'] ?? $invoice['amount'] ?? 0, 2) ?></td>
                            <td><?= date('d/m/Y', strtotime($invoice['due_date'])) ?></td>
                            <td>
                                <span class="badge bg-<?= $invoice['status'] === 'paid' ? 'success' : 
                                    ($invoice['status'] === 'pending' ? 'warning' : 
                                    ($invoice['status'] === 'cancelled' ? 'danger' : 
                                    ($invoice['status'] === 'expired' ? 'secondary' : 'info'))) ?>">
                                    <?= ucfirst($invoice['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= site_url('invoices/view/' . $invoice['uuid']) ?>" 
                                       class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                    <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
                                        <a href="<?= site_url('invoices/edit/' . $invoice['uuid']) ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Editar
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal"
                                                data-uuid="<?= $invoice['uuid'] ?>"
                                                data-number="<?= esc($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A') ?>">
                                            <i class="bi bi-trash"></i> Eliminar
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No hay facturas para mostrar</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (isset($pager)): ?>
            <div class="mt-4">
                <?= $pager->links() ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirmar Eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ¿Está seguro que desea eliminar la factura <span id="invoiceNumber"></span>?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="deleteForm" action="" method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle delete modal
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const invoiceId = button.getAttribute('data-uuid');
            const invoiceNumber = button.getAttribute('data-number');
            
            document.getElementById('invoiceNumber').textContent = invoiceNumber;
            document.getElementById('deleteForm').action = `<?= site_url('invoices/delete/') ?>${invoiceId}`;
        });
    }
    
    // Handle organization filter
    const organizationSelect = document.getElementById('organization_id');
    if (organizationSelect) {
        organizationSelect.addEventListener('change', function() {
            this.form.submit();
        });
    }
});
</script>
<?= $this->endSection() ?>