<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Cuotas<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Cuotas</h1>
        
        <div>
            <a href="<?= site_url('instalments') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-clockwise"></i> Actualizar
            </a>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="<?= site_url('instalments') ?>" method="get" class="row g-3">
                <!-- Filtro por Cartera -->
                <div class="col-md-3">
                    <label for="portfolio_id" class="form-label">Cartera</label>
                    <select name="portfolio_id" id="portfolio_id" class="form-select">
                        <option value="">Todas las carteras</option>
                        <?php foreach ($portfolios as $portfolio): ?>
                            <option value="<?= $portfolio['id'] ?>" <?= $selectedPortfolio == $portfolio['id'] ? 'selected' : '' ?>>
                                <?= esc($portfolio['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filtro por Estado -->
                <div class="col-md-3">
                    <label for="status" class="form-label">Estado</label>
                    <select name="status" id="status" class="form-select">
                        <option value="all" <?= $selectedStatus === 'all' ? 'selected' : '' ?>>Todos los estados</option>
                        <option value="pending" <?= $selectedStatus === 'pending' ? 'selected' : '' ?>>Pendientes</option>
                        <option value="paid" <?= $selectedStatus === 'paid' ? 'selected' : '' ?>>Pagadas</option>
                        <option value="cancelled" <?= $selectedStatus === 'cancelled' ? 'selected' : '' ?>>Canceladas</option>
                    </select>
                </div>
                
                <!-- Filtro por Fecha de Vencimiento -->
                <div class="col-md-3">
                    <label for="due_date" class="form-label">Vencimiento</label>
                    <select name="due_date" id="due_date" class="form-select">
                        <option value="all" <?= $selectedDueDate === 'all' ? 'selected' : '' ?>>Todas las fechas</option>
                        <option value="overdue" <?= $selectedDueDate === 'overdue' ? 'selected' : '' ?>>Vencidas</option>
                        <option value="upcoming" <?= $selectedDueDate === 'upcoming' ? 'selected' : '' ?>>Por vencer</option>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-filter"></i> Filtrar
                    </button>
                    <a href="<?= site_url('instalments') ?>" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-x-circle"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tabla de Cuotas -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($instalments)): ?>
                <div class="alert alert-info">
                    No se encontraron cuotas con los filtros seleccionados.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Factura</th>
                                <th>Cliente</th>
                                <th>Cuota</th>
                                <th>Monto</th>
                                <th>Pagado</th>
                                <th>Pendiente</th>
                                <th>Vencimiento</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($instalments as $instalment): 
                                // Calcular clase CSS para la fila
                                $rowClass = '';
                                if ($instalment['status'] === 'paid') {
                                    $rowClass = 'table-success';
                                } elseif ($instalment['is_overdue']) {
                                    $rowClass = 'table-danger';
                                }
                            ?>
                                <tr class="<?= $rowClass ?>">
                                    <td>
                                        <a href="<?= site_url('invoices/view/' . $instalment['invoice_uuid']) ?>">
                                            <?= esc($instalment['invoice_number']) ?>
                                        </a>
                                    </td>
                                    <td><?= esc($instalment['client_name']) ?></td>
                                    <td><?= $instalment['number'] ?></td>
                                    <td>S/ <?= number_format($instalment['amount'], 2) ?></td>
                                    <td>S/ <?= number_format($instalment['paid_amount'], 2) ?></td>
                                    <td>S/ <?= number_format($instalment['remaining_amount'], 2) ?></td>
                                    <td><?= date('d/m/Y', strtotime($instalment['due_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $instalment['status'] === 'paid' ? 'success' : ($instalment['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                            <?= ucfirst($instalment['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($instalment['status'] === 'pending' && $auth->hasAnyRole(['superadmin', 'admin'])): ?>
                                            <div class="btn-group">
                                                <a href="<?= site_url('payments/create/' . $instalment['invoice_uuid'] . '/' . $instalment['id']) ?>" class="btn btn-sm btn-success">
                                                    <i class="bi bi-cash"></i> Pagar
                                                </a>
                                                <?php
                                                // Get organization to check if Ligo is enabled
                                                $organizationModel = new \App\Models\OrganizationModel();
                                                $organization = $organizationModel->find($organizationId);
                                                if (isset($organization['ligo_enabled']) && $organization['ligo_enabled']): ?>
                                                    <a href="<?= site_url('payment/ligo/qr/' . $instalment['invoice_uuid'] . '/' . $instalment['id']) ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-qr-code"></i> QR Ligo
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
