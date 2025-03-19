<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Detalles de Cuenta por Cobrar<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Detalles de Cuenta por Cobrar</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= site_url('invoices') ?>">Cuentas por Cobrar</a></li>
                <li class="breadcrumb-item active">Detalles</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                Información de la Cuenta
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Número de Factura:</div>
                    <div class="col-8"><?= esc($invoice['invoice_number']) ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Concepto:</div>
                    <div class="col-8"><?= esc($invoice['concept']) ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Monto:</div>
                    <div class="col-8">$<?= number_format($invoice['amount'], 2) ?></div>
                </div>
                
                <?php if (isset($payment_info)): ?>
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Total Pagado:</div>
                    <div class="col-8">$<?= number_format($payment_info['total_paid'], 2) ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Saldo Pendiente:</div>
                    <div class="col-8">$<?= number_format($payment_info['remaining'], 2) ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-12">
                        <div class="progress" style="height: 20px;">
                            <?php 
                            $percent = ($payment_info['total_paid'] / $invoice['amount']) * 100;
                            $percent = min(100, $percent); // Cap at 100%
                            ?>
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?= $percent ?>%;" 
                                 aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                                <?= round($percent) ?>%
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Fecha Vencimiento:</div>
                    <div class="col-8"><?= date('d/m/Y', strtotime($invoice['due_date'])) ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Estado:</div>
                    <div class="col-8">
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
                    </div>
                </div>
                <?php if ($invoice['external_id']): ?>
                <div class="row mb-2">
                    <div class="col-4 fw-bold">ID Externo:</div>
                    <div class="col-8"><?= esc($invoice['external_id']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($invoice['notes']): ?>
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Notas:</div>
                    <div class="col-8"><?= nl2br(esc($invoice['notes'])) ?></div>
                </div>
                <?php endif; ?>
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Fecha Creación:</div>
                    <div class="col-8"><?= date('d/m/Y H:i', strtotime($invoice['created_at'])) ?></div>
                </div>
                <div class="row">
                    <div class="col-4 fw-bold">Última Actualización:</div>
                    <div class="col-8"><?= date('d/m/Y H:i', strtotime($invoice['updated_at'])) ?></div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <?php if ($auth->hasAnyRole(['superadmin', 'admin']) && $invoice['status'] !== 'paid'): ?>
                    <a href="<?= site_url('invoices/edit/' . $invoice['id']) ?>" class="btn btn-primary">
                        Editar
                    </a>
                <?php endif; ?>
                
                <?php if ($invoice['status'] === 'pending'): ?>
                    <a href="<?= site_url('payments/create/' . $invoice['id']) ?>" class="btn btn-success">
                        Registrar Pago
                    </a>
                <?php endif; ?>
                
                <a href="<?= site_url('invoices') ?>" class="btn btn-secondary">
                    Volver
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                Información del Cliente
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Nombre/Razón Social:</div>
                    <div class="col-8"><?= isset($client['business_name']) ? esc($client['business_name']) : 'No disponible' ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 fw-bold">RUC/CI:</div>
                    <div class="col-8"><?= isset($client['document_number']) ? esc($client['document_number']) : 'No disponible' ?></div>
                </div>
                <?php if(isset($client['contact_name']) && !empty($client['contact_name'])): ?>
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Contacto:</div>
                    <div class="col-8"><?= esc($client['contact_name']) ?></div>
                </div>
                <?php endif; ?>
                <?php if(isset($client['phone']) && !empty($client['phone'])): ?>
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Teléfono:</div>
                    <div class="col-8"><?= esc($client['phone']) ?></div>
                </div>
                <?php endif; ?>
                <?php if(isset($client['email']) && !empty($client['email'])): ?>
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Email:</div>
                    <div class="col-8"><?= esc($client['email']) ?></div>
                </div>
                <?php endif; ?>
                <?php if(isset($client['address']) && !empty($client['address'])): ?>
                <div class="row mb-2">
                    <div class="col-4 fw-bold">Dirección:</div>
                    <div class="col-8"><?= nl2br(esc($client['address'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <?php if (isset($client['id']) && $client['id'] > 0): ?>
                <a href="<?= site_url('clients/view/' . $client['id']) ?>" class="btn btn-info">
                    Ver Detalles del Cliente
                </a>
                <?php else: ?>
                <button class="btn btn-info" disabled>Ver Detalles del Cliente</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Pagos Registrados</h5>
                <?php if ($invoice['status'] === 'pending'): ?>
                    <a href="<?= site_url('payments/create/' . $invoice['id']) ?>" class="btn btn-sm btn-success">
                        Registrar Pago
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (isset($payment_info) && $payment_info['total_paid'] > 0): ?>
                <div class="alert alert-info mb-3">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Total Factura:</strong> $<?= number_format($invoice['amount'], 2) ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Total Pagado:</strong> $<?= number_format($payment_info['total_paid'], 2) ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Saldo Pendiente:</strong> $<?= number_format($payment_info['remaining'], 2) ?>
                        </div>
                    </div>
                    <?php if ($payment_info['is_fully_paid']): ?>
                    <div class="mt-2 badge bg-success">Cuenta completamente pagada</div>
                    <?php else: ?>
                    <div class="mt-2 badge bg-warning text-dark">Cuenta con pagos parciales</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (empty($payments)): ?>
                    <div class="alert alert-info mb-0">
                        No se han registrado pagos para esta cuenta por cobrar.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th>Método</th>
                                    <th>Referencia</th>
                                    <th>Cobrador</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($payment['payment_date'])) ?></td>
                                        <td>$<?= number_format($payment['amount'], 2) ?></td>
                                        <td><?= esc($payment['payment_method']) ?></td>
                                        <td><?= esc($payment['reference_code']) ?></td>
                                        <td><?= esc($payment['collector_name']) ?></td>
                                        <td>
                                            <?php if ($payment['status'] === 'completed'): ?>
                                                <span class="badge bg-success">Completado</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?= site_url('payments/view/' . $payment['id']) ?>" class="btn btn-sm btn-info">
                                                Ver
                                            </a>
                                            <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="confirmDeletePayment(<?= $payment['id'] ?>)">
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
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmación de Eliminación de Pago -->
<div class="modal fade" id="deletePaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ¿Está seguro que desea eliminar este pago?
                <br><br>
                <strong>Esta acción no se puede deshacer.</strong>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="deletePaymentLink" class="btn btn-danger">Eliminar</a>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDeletePayment(id) {
        document.getElementById('deletePaymentLink').href = '<?= site_url('payments/delete/') ?>' + id;
        
        var modal = new bootstrap.Modal(document.getElementById('deletePaymentModal'));
        modal.show();
    }
</script>
<?= $this->endSection() ?>