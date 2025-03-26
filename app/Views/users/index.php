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
                            <a href="<?= site_url('users?sort=id&order=' . ($currentSort == 'id' && $currentOrder == 'asc' ? 'desc' : 'asc') . ($filterOrgId ? '&organization_id=' . $filterOrgId : '')) ?>">
                                ID
                                <?php if ($currentSort == 'id'): ?>
                                    <i class="bi bi-arrow-<?= $currentOrder == 'asc' ? 'up' : 'down' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= site_url('users?sort=name&order=' . ($currentSort == 'name' && $currentOrder == 'asc' ? 'desc' : 'asc') . ($filterOrgId ? '&organization_id=' . $filterOrgId : '')) ?>">
                                Nombre
                                <?php if ($currentSort == 'name'): ?>
                                    <i class="bi bi-arrow-<?= $currentOrder == 'asc' ? 'up' : 'down' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= site_url('users?sort=email&order=' . ($currentSort == 'email' && $currentOrder == 'asc' ? 'desc' : 'asc') . ($filterOrgId ? '&organization_id=' . $filterOrgId : '')) ?>">
                                Email
                                <?php if ($currentSort == 'email'): ?>
                                    <i class="bi bi-arrow-<?= $currentOrder == 'asc' ? 'up' : 'down' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Teléfono</th>
                        <th>Rol</th>
                        <?php if ($auth->hasRole('superadmin')): ?>
                            <th>
                                <a href="<?= site_url('users?sort=organization&order=' . ($currentSort == 'organization' && $currentOrder == 'asc' ? 'desc' : 'asc') . ($filterOrgId ? '&organization_id=' . $filterOrgId : '')) ?>">
                                    Organización
                                    <?php if ($currentSort == 'organization'): ?>
                                        <i class="bi bi-arrow-<?= $currentOrder == 'asc' ? 'up' : 'down' ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                        <?php endif; ?>
                        <th>
                            <a href="<?= site_url('users?sort=status&order=' . ($currentSort == 'status' && $currentOrder == 'asc' ? 'desc' : 'asc') . ($filterOrgId ? '&organization_id=' . $filterOrgId : '')) ?>">
                                Estado
                                <?php if ($currentSort == 'status'): ?>
                                    <i class="bi bi-arrow-<?= $currentOrder == 'asc' ? 'up' : 'down' ?>"></i>
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
                                <td><?= $user['id'] ?></td>
                                <td><?= $user['name'] ?></td>
                                <td><?= $user['email'] ?></td>
                                <td><?= $user['phone'] ?? 'N/A' ?></td>
                                <td><?= ucfirst($user['role']) ?></td>
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
                                    <a href="<?= site_url('users/' . $user['id']) ?>" class="btn btn-sm btn-info">Ver</a>
                                    <a href="<?= site_url('users/' . $user['id'] . '/edit') ?>" class="btn btn-sm btn-primary">Editar</a>
                                    <?php if ($user['id'] != $auth->user()['id']): ?>
                                        <a href="<?= site_url('users/' . $user['id'] . '/delete') ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro de eliminar este usuario?')">Eliminar</a>
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
<?= $this->endSection() ?>