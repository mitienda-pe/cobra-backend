<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Cliente: <?= $client['business_name'] ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Detalles del Cliente</h3>
                    <div>
                        <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
                            <a href="<?= site_url('clients/edit/' . $client['id']) ?>" class="btn btn-primary me-2">Editar</a>
                        <?php endif; ?>
                        <a href="<?= site_url('clients') ?>" class="btn btn-secondary">Volver</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Información General</h5>
                        <table class="table">
                            <tr>
                                <th width="30%">Nombre Comercial:</th>
                                <td><?= $client['business_name'] ?></td>
                            </tr>
                            <tr>
                                <th>Razón Social:</th>
                                <td><?= $client['legal_name'] ?></td>
                            </tr>
                            <tr>
                                <th>RUC/Documento:</th>
                                <td><?= $client['document_number'] ?></td>
                            </tr>
                            <tr>
                                <th>UUID:</th>
                                <td>
                                    <?php if (isset($client['uuid']) && !empty($client['uuid'])): ?>
                                        <div class="input-group">
                                            <input type="text" class="form-control form-control-sm" value="<?= $client['uuid'] ?>" id="client-uuid" readonly>
                                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copyToClipboard('client-uuid')">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">Identificador único para API</small>
                                    <?php else: ?>
                                        <span class="text-muted">No generado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>ID Externo:</th>
                                <td><?= $client['external_id'] ?? 'No definido' ?></td>
                            </tr>
                            <tr>
                                <th>Estado:</th>
                                <td>
                                    <?php if ($client['status'] == 'active'): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Información de Contacto</h5>
                        <table class="table">
                            <tr>
                                <th width="30%">Nombre de Contacto:</th>
                                <td><?= $client['contact_name'] ?? 'No definido' ?></td>
                            </tr>
                            <tr>
                                <th>Teléfono:</th>
                                <td><?= $client['contact_phone'] ?? 'No definido' ?></td>
                            </tr>
                            <tr>
                                <th>Dirección:</th>
                                <td><?= $client['address'] ?? 'No definida' ?></td>
                            </tr>
                            <tr>
                                <th>Ubigeo/C.P.:</th>
                                <td>
                                    <?= $client['ubigeo'] ?? 'No definido' ?>
                                    <?= $client['zip_code'] ? ' / ' . $client['zip_code'] : '' ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Coordenadas:</th>
                                <td>
                                    <?php if ($client['latitude'] && $client['longitude']): ?>
                                        <?= $client['latitude'] ?>, <?= $client['longitude'] ?>
                                        <a href="https://maps.google.com/?q=<?= $client['latitude'] ?>,<?= $client['longitude'] ?>" target="_blank" class="ms-2">
                                            <i class="bi bi-map"></i> Ver en mapa
                                        </a>
                                    <?php else: ?>
                                        No definidas
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h5>Carteras Asignadas</h5>
                        <?php if (empty($portfolios)): ?>
                            <p>Este cliente no está asignado a ninguna cartera.</p>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($portfolios as $portfolio): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><?= $portfolio['name'] ?></span>
                                        <a href="<?= site_url('portfolios/view/' . $portfolio['id']) ?>" class="btn btn-sm btn-info">Ver Cartera</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5>Cuentas por Cobrar</h5>
                            <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
                                <a href="<?= site_url('invoices/create?client_id=' . $client['id']) ?>" class="btn btn-sm btn-success">
                                    <i class="bi bi-plus-circle"></i> Nueva Cuenta
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (empty($invoices)): ?>
                            <p>Este cliente no tiene cuentas por cobrar registradas.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Número</th>
                                            <th>Concepto</th>
                                            <th>Monto</th>
                                            <th>Vencimiento</th>
                                            <th>Estado</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices as $invoice): ?>
                                            <tr>
                                                <td><?= $invoice['invoice_number'] ?></td>
                                                <td><?= $invoice['concept'] ?></td>
                                                <td><?= number_format($invoice['amount'], 2) ?></td>
                                                <td><?= date('d/m/Y', strtotime($invoice['due_date'])) ?></td>
                                                <td>
                                                    <?php 
                                                    switch($invoice['status']) {
                                                        case 'pending':
                                                            echo '<span class="badge bg-warning">Pendiente</span>';
                                                            break;
                                                        case 'paid':
                                                            echo '<span class="badge bg-success">Pagada</span>';
                                                            break;
                                                        case 'cancelled':
                                                            echo '<span class="badge bg-danger">Anulada</span>';
                                                            break;
                                                        case 'rejected':
                                                            echo '<span class="badge bg-danger">Rechazada</span>';
                                                            break;
                                                        case 'expired':
                                                            echo '<span class="badge bg-secondary">Vencida</span>';
                                                            break;
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <a href="<?= site_url('invoices/view/' . $invoice['id']) ?>" class="btn btn-sm btn-info">Ver</a>
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
    </div>
</div>

<script>
function copyToClipboard(elementId) {
    const copyText = document.getElementById(elementId);
    copyText.select();
    copyText.setSelectionRange(0, 99999); // For mobile devices
    navigator.clipboard.writeText(copyText.value);
    
    // Create a tooltip to show "Copied!"
    const button = document.querySelector(`#${elementId} + button`);
    const originalHtml = button.innerHTML;
    button.innerHTML = '<i class="bi bi-check"></i>';
    button.classList.add('btn-success');
    button.classList.remove('btn-outline-secondary');
    
    // Restore original after 2 seconds
    setTimeout(() => {
        button.innerHTML = originalHtml;
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-secondary');
    }, 2000);
}
</script>
<?= $this->endSection() ?>