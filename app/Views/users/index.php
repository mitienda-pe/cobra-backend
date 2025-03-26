<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Usuarios<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Gestión de Usuarios</h1>
    <a href="<?= site_url('users/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo Usuario
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>
                            <a href="<?= site_url('users?sort=uuid&order=' . ($currentSort === 'uuid' && $currentOrder === 'asc' ? 'desc' : 'asc')) ?>">
                                UUID
                                <?php if ($currentSort === 'uuid'): ?>
                                    <i class="bi bi-arrow-<?= $currentOrder === 'asc' ? 'up' : 'down' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= site_url('users?sort=name&order=' . ($currentSort === 'name' && $currentOrder === 'asc' ? 'desc' : 'asc')) ?>">
                                Nombre
                                <?php if ($currentSort === 'name'): ?>
                                    <i class="bi bi-arrow-<?= $currentOrder === 'asc' ? 'up' : 'down' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= site_url('users?sort=email&order=' . ($currentSort === 'email' && $currentOrder === 'asc' ? 'desc' : 'asc')) ?>">
                                Email
                                <?php if ($currentSort === 'email'): ?>
                                    <i class="bi bi-arrow-<?= $currentOrder === 'asc' ? 'up' : 'down' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Teléfono</th>
                        <?php if ($auth->hasRole('superadmin')): ?>
                            <th>
                                <a href="<?= site_url('users?sort=organization&order=' . ($currentSort === 'organization' && $currentOrder === 'asc' ? 'desc' : 'asc')) ?>">
                                    Organización
                                    <?php if ($currentSort === 'organization'): ?>
                                        <i class="bi bi-arrow-<?= $currentOrder === 'asc' ? 'up' : 'down' ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                        <?php endif; ?>
                        <th>
                            <a href="<?= site_url('users?sort=status&order=' . ($currentSort === 'status' && $currentOrder === 'asc' ? 'desc' : 'asc')) ?>">
                                Estado
                                <?php if ($currentSort === 'status'): ?>
                                    <i class="bi bi-arrow-<?= $currentOrder === 'asc' ? 'up' : 'down' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="<?= $auth->hasRole('superadmin') ? 8 : 7 ?>" class="text-center">No hay usuarios registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['uuid'] ?></td>
                                <td><?= $user['name'] ?></td>
                                <td><?= $user['email'] ?></td>
                                <td><?= $user['phone'] ?? 'N/A' ?></td>
                                <?php if ($auth->hasRole('superadmin')): ?>
                                    <td><?= $user['organization_name'] ?? 'N/A' ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($user['status'] == 'active'): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= site_url('users/' . $user['uuid']) ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                    <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
                                        <a href="<?= site_url('users/' . $user['uuid'] . '/edit') ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Editar
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-uuid="<?= $user['uuid'] ?>" data-name="<?= esc($user['name']) ?>">
                                            <i class="bi bi-trash"></i> Eliminar
                                        </button>
                                    <?php endif; ?>
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
                ¿Está seguro que desea eliminar el usuario <strong id="userName"></strong>?
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

                deleteModal.querySelector('#userName').textContent = name;
                deleteModal.querySelector('#deleteForm').action = '<?= site_url('users/') ?>' + uuid + '/delete';
            });
        }
    });
</script>
<?= $this->endSection() ?>