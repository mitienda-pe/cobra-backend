<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Detalle de Cartera<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Cartera: <?= $portfolio['name'] ?></h1>
    <div>
        <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
            <a href="<?= site_url('portfolios/' . $portfolio['uuid'] . '/edit') ?>" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Editar
            </a>
        <?php endif; ?>
        <a href="<?= site_url('portfolios') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5>Información de la Cartera</h5>
            </div>
            <div class="card-body">
                <p><strong>ID:</strong> <?= $portfolio['uuid'] ?></p>
                <p><strong>Nombre:</strong> <?= $portfolio['name'] ?></p>
                <p><strong>Descripción:</strong> <?= $portfolio['description'] ?: 'N/A' ?></p>
                <p>
                    <strong>Estado:</strong>
                    <?php if ($portfolio['status'] == 'active'): ?>
                        <span class="badge bg-success">Activa</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Inactiva</span>
                    <?php endif; ?>
                </p>
                <p><strong>Organización:</strong> 
                    <?php 
                    $db = \Config\Database::connect();
                    $org = $db->table('organizations')->where('id', $portfolio['organization_id'])->get()->getRowArray();
                    echo $org ? $org['name'] : 'N/A';
                    ?>
                </p>
                <p><strong>Fecha de Creación:</strong> <?= date('d/m/Y H:i', strtotime($portfolio['created_at'])) ?></p>
                <p><strong>Última Actualización:</strong> <?= date('d/m/Y H:i', strtotime($portfolio['updated_at'])) ?></p>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5>Usuarios Asignados</h5>
            </div>
            <div class="card-body">
                <?php if (empty($assignedUsers)): ?>
                    <p class="text-muted">No hay usuarios asignados a esta cartera.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignedUsers as $user): ?>
                                    <tr>
                                        <td><?= $user['uuid'] ?></td>
                                        <td><?= $user['name'] ?></td>
                                        <td><?= $user['email'] ?></td>
                                        <td><?= ucfirst($user['role']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Clientes Asignados</h5>
                <div>
                    <input type="text" id="clientSearch" class="form-control form-control-sm" placeholder="Buscar cliente...">
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($assignedClients)): ?>
                    <p class="text-muted">No hay clientes asignados a esta cartera.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="clientsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre/Razón Social</th>
                                    <th>RUC/DNI</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignedClients as $client): ?>
                                    <tr>
                                        <td><?= $client['uuid'] ?></td>
                                        <td><?= $client['business_name'] ?></td>
                                        <td><?= $client['document_number'] ?></td>
                                        <td>
                                            <a href="<?= site_url('clients/' . $client['uuid']) ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Facturas de la Cartera</h5>
                <div>
                    <form action="<?= current_url() ?>" method="get" class="d-flex">
                        <select name="client_id" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                            <option value="">Todos los clientes</option>
                            <?php foreach ($assignedClients as $client): ?>
                                <option value="<?= $client['uuid'] ?>" <?= $request->getGet('client_id') == $client['uuid'] ? 'selected' : '' ?>>
                                    <?= $client['business_name'] ?> (<?= $client['document_number'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <?php 
                // Add client filter info if a client is selected
                $clientId = $request->getGet('client_id');
                if ($clientId) {
                    foreach ($assignedClients as $client) {
                        if ($client['uuid'] == $clientId) {
                            echo '<div class="alert alert-info mb-3">
                                    <strong>Mostrando facturas de:</strong> ' . esc($client['business_name']) . ' (' . esc($client['document_number']) . ')
                                    <a href="' . current_url() . '" class="ms-2 btn btn-sm btn-outline-dark">Eliminar filtro</a>
                                  </div>';
                            break;
                        }
                    }
                }
                ?>
                
                <?php if (empty($invoices)): ?>
                    <p class="text-muted">No hay facturas asociadas a esta cartera<?= $clientId ? ' para el cliente seleccionado' : '' ?>.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Número</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td><?= $invoice['id'] ?></td>
                                        <td><?= $invoice['client_name'] ?></td>
                                        <td><?= $invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A' ?></td>
                                        <td><?= number_format($invoice['amount'], 2) ?></td>
                                        <td>
                                            <?php if ($invoice['status'] == 'paid'): ?>
                                                <span class="badge bg-success">Pagada</span>
                                            <?php elseif ($invoice['status'] == 'partial'): ?>
                                                <span class="badge bg-warning">Pago Parcial</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?= site_url('invoices/view/' . $invoice['id']) ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i> Ver
                                            </a>
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

<?= $this->section('scripts') ?>
<script>
// Client search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('clientSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('clientsTable');
            
            if (table) {
                const rows = table.querySelectorAll('tbody tr');
                
                rows.forEach(function(row) {
                    const businessName = row.cells[1].textContent.toLowerCase();
                    const documentNumber = row.cells[2].textContent.toLowerCase();
                    
                    if (businessName.includes(searchTerm) || documentNumber.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
        });
    }
});
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>