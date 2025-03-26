<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Organizaciones<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Gestión de Organizaciones</h1>
    <a href="<?= site_url('organizations/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nueva Organización
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>UUID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Fecha de Creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($organizations)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No hay organizaciones registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($organizations as $org): ?>
                            <tr>
                                <td><?= $org['uuid'] ?></td>
                                <td><?= $org['name'] ?></td>
                                <td><?= $org['description'] ?? 'N/A' ?></td>
                                <td>
                                    <?php if ($org['status'] == 'active'): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($org['created_at'])) ?></td>
                                <td>
                                    <a href="<?= site_url('organizations/' . $org['uuid']) ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                    <a href="<?= site_url('organizations/' . $org['uuid'] . '/edit') ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-uuid="<?= $org['uuid'] ?>" data-name="<?= esc($org['name']) ?>">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
                ¿Está seguro que desea eliminar la organización <strong id="orgName"></strong>?
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const uuid = button.getAttribute('data-uuid');
            const name = button.getAttribute('data-name');
            
            deleteModal.querySelector('#orgName').textContent = name;
            deleteModal.querySelector('#deleteForm').action = '<?= site_url('organizations/') ?>' + uuid + '/delete';
        });
    }
});
</script>
<?= $this->endSection() ?>