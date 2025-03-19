<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Ver Organización<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Detalles de la Organización</h3>
                    <a href="<?= site_url('organizations') ?>" class="btn btn-secondary">Volver</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Información General</h5>
                        <table class="table">
                            <tr>
                                <th style="width: 150px;">ID:</th>
                                <td><?= $organization['id'] ?></td>
                            </tr>
                            <tr>
                                <th>Nombre:</th>
                                <td><?= $organization['name'] ?></td>
                            </tr>
                            <tr>
                                <th>Descripción:</th>
                                <td><?= $organization['description'] ?? 'N/A' ?></td>
                            </tr>
                            <tr>
                                <th>Estado:</th>
                                <td>
                                    <?php if ($organization['status'] == 'active'): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Fecha de Creación:</th>
                                <td><?= date('d/m/Y H:i', strtotime($organization['created_at'])) ?></td>
                            </tr>
                            <tr>
                                <th>Última Actualización:</th>
                                <td><?= date('d/m/Y H:i', strtotime($organization['updated_at'])) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Acciones</h5>
                        <div class="d-flex gap-2">
                            <a href="<?= site_url('organizations/edit/' . $organization['id']) ?>" class="btn btn-primary">Editar Organización</a>
                            <a href="<?= site_url('organizations/delete/' . $organization['id']) ?>" class="btn btn-danger" onclick="return confirm('¿Está seguro de eliminar esta organización?')">Eliminar Organización</a>
                        </div>
                    </div>
                </div>
                
                <h5>Usuarios de la Organización</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No hay usuarios asociados a esta organización.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= $user['id'] ?></td>
                                        <td><?= $user['name'] ?></td>
                                        <td><?= $user['email'] ?></td>
                                        <td>
                                            <?php if ($user['role'] == 'admin'): ?>
                                                <span class="badge bg-primary">Administrador</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Usuario</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['status'] == 'active'): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?= site_url('users/edit/' . $user['id']) ?>" class="btn btn-sm btn-primary">Editar</a>
                                            <a href="<?= site_url('users/delete/' . $user['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro de eliminar este usuario?')">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <a href="<?= site_url('users/create') ?>" class="btn btn-success">Agregar Usuario</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>