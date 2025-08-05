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
        <a href="<?= site_url('invoices/import') ?>" class="btn btn-outline-primary">
            <i class="bi bi-upload"></i> Importar
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3" id="filterForm">
            <div class="col-md-3">
                <label for="status" class="form-label">Estado</label>
                <select name="status" id="status" class="form-select">
                    <option value="all" <?= ($current_status ?? '') === 'all' ? 'selected' : '' ?>>Todos los estados</option>
                    <option value="pending" <?= ($current_status ?? '') === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="paid" <?= ($current_status ?? '') === 'paid' ? 'selected' : '' ?>>Pagado</option>
                    <option value="cancelled" <?= ($current_status ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                    <option value="expired" <?= ($current_status ?? '') === 'expired' ? 'selected' : '' ?>>Vencido</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Acciones</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                    <a href="<?= site_url('invoices') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Limpiar
                    </a>
                </div>
            </div>
            <!-- Hidden fields to preserve sorting -->
            <input type="hidden" name="sort" value="<?= $current_sort ?? '' ?>">
            <input type="hidden" name="order" value="<?= $current_order ?? '' ?>">
        </form>
    </div>
</div>


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
            <table class="table table-striped table-hover" id="invoicesTable">
                <thead class="table-dark">
                    <tr>
                        <?php 
                        $currentSort = $current_sort ?? '';
                        $currentOrder = $current_order ?? 'DESC';
                        $nextOrder = ($currentOrder === 'ASC') ? 'DESC' : 'ASC';
                        
                        function getSortIcon($field, $currentSort, $currentOrder) {
                            if ($currentSort === $field) {
                                return $currentOrder === 'ASC' ? '<i class="bi bi-sort-up"></i>' : '<i class="bi bi-sort-down"></i>';
                            }
                            return '<i class="bi bi-sort"></i>';
                        }
                        
                        function getSortUrl($field, $currentSort, $currentOrder, $currentStatus = null) {
                            $nextOrder = ($currentSort === $field && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
                            $params = ['sort' => $field, 'order' => $nextOrder];
                            if ($currentStatus && $currentStatus !== 'all') {
                                $params['status'] = $currentStatus;
                            }
                            return site_url('invoices?' . http_build_query($params));
                        }
                        ?>
                        
                        <th>
                            <a href="<?= getSortUrl('invoices.invoice_number', $currentSort, $currentOrder, $current_status ?? null) ?>" 
                               class="text-white text-decoration-none">
                                Número <?= getSortIcon('invoices.invoice_number', $currentSort, $currentOrder) ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('clients.business_name', $currentSort, $currentOrder, $current_status ?? null) ?>" 
                               class="text-white text-decoration-none">
                                Cliente <?= getSortIcon('clients.business_name', $currentSort, $currentOrder) ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('clients.document_number', $currentSort, $currentOrder, $current_status ?? null) ?>" 
                               class="text-white text-decoration-none">
                                RUC <?= getSortIcon('clients.document_number', $currentSort, $currentOrder) ?>
                            </a>
                        </th>
                        <th>Concepto</th>
                        <th>
                            <a href="<?= getSortUrl('invoices.total_amount', $currentSort, $currentOrder, $current_status ?? null) ?>" 
                               class="text-white text-decoration-none">
                                Importe <?= getSortIcon('invoices.total_amount', $currentSort, $currentOrder) ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('invoices.due_date', $currentSort, $currentOrder, $current_status ?? null) ?>" 
                               class="text-white text-decoration-none">
                                Vencimiento <?= getSortIcon('invoices.due_date', $currentSort, $currentOrder) ?>
                            </a>
                        </th>
                        <th>Cuotas</th>
                        <th>
                            <a href="<?= getSortUrl('invoices.status', $currentSort, $currentOrder, $current_status ?? null) ?>" 
                               class="text-white text-decoration-none">
                                Estado <?= getSortIcon('invoices.status', $currentSort, $currentOrder) ?>
                            </a>
                        </th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td>
                                <strong><?= esc($invoice['invoice_number'] ?? 'N/A') ?></strong>
                                <?php if ($invoice['issue_date']): ?>
                                    <br><small class="text-muted"><?= date('d/m/Y', strtotime($invoice['issue_date'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($invoice['business_name'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ($invoice['client_document']): ?>
                                    <code><?= esc($invoice['client_document']) ?></code>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($invoice['concept']) ?></td>
                            <td><strong>S/ <?= number_format($invoice['total_amount'] ?? $invoice['amount'] ?? 0, 2) ?></strong></td>
                            <td>
                                <?php 
                                $dueDate = strtotime($invoice['due_date']);
                                $today = time();
                                $isOverdue = $dueDate < $today && $invoice['status'] !== 'paid';
                                ?>
                                <span class="<?= $isOverdue ? 'text-danger fw-bold' : '' ?>">
                                    <?= date('d/m/Y', $dueDate) ?>
                                </span>
                                <?php if ($isOverdue): ?>
                                    <br><small class="text-danger">Vencido</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($invoice['instalments_count'] > 0): ?>
                                    <span class="badge bg-info"><?= $invoice['instalments_count'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $invoice['status'] === 'paid' ? 'success' : 
                                    ($invoice['status'] === 'pending' ? 'warning' : 
                                    ($invoice['status'] === 'cancelled' ? 'danger' : 
                                    ($invoice['status'] === 'expired' ? 'secondary' : 'info'))) ?>">
                                    <?= match($invoice['status']) {
                                        'paid' => 'Pagado',
                                        'pending' => 'Pendiente',
                                        'cancelled' => 'Cancelado',
                                        'expired' => 'Vencido',
                                        default => ucfirst($invoice['status'])
                                    } ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= site_url('invoices/view/' . $invoice['uuid']) ?>" 
                                       class="btn btn-sm btn-info" title="Ver detalle">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
                                        <a href="<?= site_url('invoices/edit/' . $invoice['uuid']) ?>" 
                                           class="btn btn-sm btn-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal"
                                                data-uuid="<?= $invoice['uuid'] ?>"
                                                data-number="<?= esc($invoice['invoice_number'] ?? 'N/A') ?>"
                                                title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <i class="bi bi-receipt display-6 text-muted"></i>
                                <p class="text-muted mt-2">No hay facturas para mostrar</p>
                            </td>
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