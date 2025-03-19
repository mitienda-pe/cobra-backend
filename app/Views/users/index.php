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
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <?php if ($auth->hasRole('superadmin')): ?>
                            <th>Organización</th>
                        <?php endif; ?>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="<?= $auth->hasRole('superadmin') ? 7 : 6 ?>" class="text-center">No hay usuarios registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= $user['name'] ?></td>
                                <td><?= $user['email'] ?></td>
                                <td>
                                    <?php if ($user['role'] == 'superadmin'): ?>
                                        <span class="badge bg-danger">Superadmin</span>
                                    <?php elseif ($user['role'] == 'admin'): ?>
                                        <span class="badge bg-primary">Administrador</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Usuario</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($auth->hasRole('superadmin')): ?>
                                    <td>
                                        <?php if ($user['organization_id']): ?>
                                            <a href="<?= site_url('organizations/view/' . $user['organization_id']) ?>">
                                                Ver Organización
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($user['status'] == 'active'): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= site_url('users/edit/' . $user['id']) ?>" class="btn btn-sm btn-primary">Editar</a>
                                    <?php if ($user['id'] != $auth->user()['id']): ?>
                                        <a href="<?= site_url('users/delete/' . $user['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro de eliminar este usuario?')">Eliminar</a>
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