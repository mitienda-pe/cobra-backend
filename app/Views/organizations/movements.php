<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-list-alt mr-2"></i>
                        Movimientos - <?= esc($organization['name']) ?>
                    </h3>
                    <div class="btn-group">
                        <a href="<?= site_url('organizations/account/' . $organization['uuid']) ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-chart-line"></i> Estado de Cuenta
                        </a>
                        <button type="button" class="btn btn-success btn-sm" onclick="exportMovements()">
                            <i class="fas fa-download"></i> Exportar CSV
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <form method="GET" class="form-inline">
                                <div class="form-group mr-3">
                                    <label for="date_start" class="mr-2">Desde:</label>
                                    <input type="date" class="form-control form-control-sm" id="date_start" name="date_start"
                                        value="<?= esc($dateStart) ?>">
                                </div>
                                <div class="form-group mr-3">
                                    <label for="date_end" class="mr-2">Hasta:</label>
                                    <input type="date" class="form-control form-control-sm" id="date_end" name="date_end"
                                        value="<?= esc($dateEnd) ?>">
                                </div>
                                <div class="form-group mr-3">
                                    <label for="payment_method" class="mr-2">Método:</label>
                                    <select class="form-control form-control-sm" id="payment_method" name="payment_method">
                                        <option value="">Todos</option>
                                        <option value="qr" <?= $paymentMethod === 'qr' ? 'selected' : '' ?>>QR</option>
                                        <option value="cash" <?= $paymentMethod === 'cash' ? 'selected' : '' ?>>Efectivo</option>
                                        <option value="bank_transfer" <?= $paymentMethod === 'bank_transfer' ? 'selected' : '' ?>>Transferencia</option>
                                        <option value="other" <?= $paymentMethod === 'other' ? 'selected' : '' ?>>Otros</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-filter"></i> Filtrar
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Resumen:</strong>
                                Mostrando <?= number_format(count($movements)) ?> de <?= number_format($totalMovements) ?> movimientos
                                (Página <?= $currentPage ?> de <?= $totalPages ?>)
                            </div>
                        </div>
                    </div>

                    <!-- Movements Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Método</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Factura</th>
                                    <th>Cliente</th>
                                    <th>Cuota</th>
                                    <th>Cobrador</th>
                                    <th>Referencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($movements)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3"></i><br>
                                            No se encontraron movimientos para los filtros seleccionados
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($movements as $movement): ?>
                                        <tr>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('d/m/Y', strtotime($movement['payment_date'])) ?><br>
                                                    <?= date('H:i', strtotime($movement['payment_date'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php
                                                $methodLabels = [
                                                    'qr' => '<span class="badge badge-primary"><i class="fas fa-qrcode"></i> QR</span>',
                                                    'cash' => '<span class="badge badge-success"><i class="fas fa-money-bill"></i> Efectivo</span>',
                                                    'bank_transfer' => '<span class="badge badge-info"><i class="fas fa-university"></i> Transferencia</span>',
                                                    'other' => '<span class="badge badge-secondary"><i class="fas fa-ellipsis-h"></i> Otros</span>'
                                                ];
                                                echo $methodLabels[$movement['payment_method']] ?? '<span class="badge badge-light">' . esc($movement['payment_method']) . '</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <strong>S/ <?= number_format($movement['amount'], 2) ?></strong>
                                            </td>
                                            <td>
                                                <?php
                                                $statusLabels = [
                                                    'completed' => '<span class="badge badge-success">Completado</span>',
                                                    'pending' => '<span class="badge badge-warning">Pendiente</span>',
                                                    'rejected' => '<span class="badge badge-danger">Rechazado</span>',
                                                    'cancelled' => '<span class="badge badge-secondary">Cancelado</span>'
                                                ];
                                                echo $statusLabels[$movement['status']] ?? '<span class="badge badge-light">' . esc($movement['status']) . '</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($movement['invoice_number']): ?>
                                                    <strong><?= esc($movement['invoice_number']) ?></strong><br>
                                                    <small class="text-muted"><?= esc(substr($movement['invoice_concept'], 0, 30)) ?><?= strlen($movement['invoice_concept']) > 30 ? '...' : '' ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($movement['client_name']): ?>
                                                    <strong><?= esc($movement['client_name']) ?></strong><br>
                                                    <small class="text-muted"><?= esc($movement['client_document']) ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($movement['instalment_number']): ?>
                                                    <span class="badge badge-light">Cuota <?= esc($movement['instalment_number']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $movement['collector_name'] ? esc($movement['collector_name']) : '<span class="text-muted">Sistema</span>' ?>
                                            </td>
                                            <td>
                                                <?php if ($movement['reference_code']): ?>
                                                    <small class="font-monospace"><?= esc($movement['reference_code']) ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <nav aria-label="Navegación de movimientos">
                                    <ul class="pagination justify-content-center">
                                        <!-- Previous -->
                                        <?php if ($currentPage > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="<?= current_url() ?>?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])) ?>">
                                                    <i class="fas fa-chevron-left"></i> Anterior
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <!-- Page numbers -->
                                        <?php
                                        $startPage = max(1, $currentPage - 2);
                                        $endPage = min($totalPages, $currentPage + 2);

                                        for ($i = $startPage; $i <= $endPage; $i++):
                                        ?>
                                            <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                                                <a class="page-link" href="<?= current_url() ?>?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <!-- Next -->
                                        <?php if ($currentPage < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="<?= current_url() ?>?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])) ?>">
                                                    Siguiente <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="spinner-border text-success" role="status">
                    <span class="sr-only">Cargando...</span>
                </div>
                <p class="mt-2">Exportando movimientos...</p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function exportMovements() {
        $('#loadingModal').modal('show');

        // Build URL with current filters
        const params = new URLSearchParams();
        const dateStart = document.getElementById('date_start').value;
        const dateEnd = document.getElementById('date_end').value;
        const paymentMethod = document.getElementById('payment_method').value;

        if (dateStart) params.append('date_start', dateStart);
        if (dateEnd) params.append('date_end', dateEnd);
        if (paymentMethod) params.append('payment_method', paymentMethod);

        const exportUrl = '<?= site_url('organizations/account/' . $organization['uuid'] . '/export') ?>' +
            (params.toString() ? '?' + params.toString() : '');

        // Create a temporary link to download the file
        const link = document.createElement('a');
        link.href = exportUrl;
        link.download = '';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Hide loading modal after a short delay
        setTimeout(() => {
            $('#loadingModal').modal('hide');
        }, 2000);
    }
</script>
<?= $this->endSection() ?>