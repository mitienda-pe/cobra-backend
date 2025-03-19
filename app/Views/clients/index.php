<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Clientes<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-md-6">
        <h1>Clientes</h1>
    </div>
    <div class="col-md-6 text-end">
        <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
            <a href="<?= site_url('clients/create') ?><?= (isset($selected_organization_id) && $selected_organization_id) ? '?organization_id=' . $selected_organization_id : '' ?>" class="btn btn-primary">
                <i class="bi bi-plus"></i> Nuevo Cliente
            </a>
            <a href="<?= site_url('clients/import') ?><?= (isset($selected_organization_id) && $selected_organization_id) ? '?organization_id=' . $selected_organization_id : '' ?>" class="btn btn-outline-primary">
                <i class="bi bi-upload"></i> Importar
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($clients)): ?>
    <div class="alert alert-info">
        No se encontraron clientes. <a href="<?= site_url('clients/create') ?>" class="alert-link">Crear el primer cliente</a>.
        <div class="mt-3">
            <form action="<?= site_url('clients/create') ?>" method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="business_name" value="Empresa de Prueba">
                <input type="hidden" name="legal_name" value="Empresa de Prueba S.A.">
                <input type="hidden" name="document_number" value="123456789">
                <button type="submit" class="btn btn-primary">Crear cliente de prueba</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Nombre Comercial</th>
                    <th>Razón Social</th>
                    <th>RUC/Doc</th>
                    <th>Contacto</th>
                    <th>Teléfono</th>
                    <?php if (isset($organizations) && $auth->hasRole('superadmin') && !isset($selected_organization_id)): ?>
                    <th>Organización</th>
                    <?php endif; ?>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><?= esc($client['business_name']) ?></td>
                        <td><?= esc($client['legal_name']) ?></td>
                        <td><?= esc($client['document_number']) ?></td>
                        <td><?= esc($client['contact_name']) ?></td>
                        <td><?= esc($client['contact_phone']) ?></td>
                        <?php if (isset($organizations) && $auth->hasRole('superadmin') && !isset($selected_organization_id)): ?>
                        <td>
                            <?php 
                                // Find organization name
                                $orgName = 'Desconocida';
                                foreach ($organizations as $org) {
                                    if ($org['id'] == $client['organization_id']) {
                                        $orgName = $org['name'];
                                        break;
                                    }
                                }
                                echo esc($orgName);
                            ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <?php if ($client['status'] == 'active'): ?>
                                <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= site_url('clients/view/' . $client['id']) ?>" class="btn btn-sm btn-info">
                                Ver
                            </a>
                            <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
                                <a href="<?= site_url('clients/edit/' . $client['id']) ?>" class="btn btn-sm btn-primary">
                                    Editar
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $client['id'] ?>" data-name="<?= esc($client['business_name']) ?>">
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

<!-- Modal de confirmación de eliminación -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirmar Eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ¿Está seguro que desea eliminar a <span id="clientName" class="fw-bold"></span>?
                <br><br>
                <div class="alert alert-warning">
                    Esta acción no se puede deshacer y eliminará todos los datos relacionados con este cliente.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="deleteBtn" class="btn btn-danger">Eliminar</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                
                document.getElementById('clientName').textContent = name;
                document.getElementById('deleteBtn').href = '<?= site_url('clients/delete/') ?>' + id;
            });
        }
    });
</script>
<?= $this->endSection() ?>