<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Ver Usuario<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Detalles del Usuario</h3>
                    <div>
                        <?php if ($auth->hasRole('superadmin') || ($auth->hasRole('admin') && $auth->organizationId() == $user['organization_id'])): ?>
                            <a href="<?= site_url('users/' . $user['uuid'] . '/edit') ?>" class="btn btn-primary">
                                <i class="bi bi-pencil"></i> Editar
                            </a>
                        <?php endif; ?>
                        <a href="<?= site_url('users') ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-4">Información Personal</h5>
                        <dl class="row">
                            <dt class="col-sm-4">Nombre</dt>
                            <dd class="col-sm-8"><?= esc($user['name']) ?></dd>
                            
                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8"><?= esc($user['email']) ?></dd>
                            
                            <dt class="col-sm-4">Teléfono</dt>
                            <dd class="col-sm-8"><?= esc($user['phone']) ?></dd>
                            
                            <dt class="col-sm-4">Estado</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-<?= $user['status'] == 'active' ? 'success' : 'danger' ?>">
                                    <?= $user['status'] == 'active' ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </dd>
                        </dl>
                    </div>
                    
                    <div class="col-md-6">
                        <h5 class="mb-4">Información de Sistema</h5>
                        <dl class="row">
                            <dt class="col-sm-4">Rol</dt>
                            <dd class="col-sm-8">
                                <?php
                                $roleBadgeClass = [
                                    'superadmin' => 'bg-danger',
                                    'admin' => 'bg-warning',
                                    'user' => 'bg-info'
                                ];
                                $roleNames = [
                                    'superadmin' => 'Superadmin',
                                    'admin' => 'Administrador',
                                    'user' => 'Usuario'
                                ];
                                ?>
                                <span class="badge <?= $roleBadgeClass[$user['role']] ?>">
                                    <?= $roleNames[$user['role']] ?>
                                </span>
                            </dd>
                            
                            <?php if (isset($user['organization'])): ?>
                            <dt class="col-sm-4">Organización</dt>
                            <dd class="col-sm-8">
                                <?= esc($user['organization']['name']) ?>
                                <?php if ($user['organization']['status'] == 'inactive'): ?>
                                    <span class="badge bg-warning">Inactiva</span>
                                <?php endif; ?>
                            </dd>
                            <?php endif; ?>
                            
                            <dt class="col-sm-4">Creado</dt>
                            <dd class="col-sm-8"><?= $user['created_at'] ?></dd>
                            
                            <dt class="col-sm-4">Actualizado</dt>
                            <dd class="col-sm-8"><?= $user['updated_at'] ?></dd>
                        </dl>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <h5 class="mb-4">Carteras Asignadas</h5>
                        <?php if (!empty($portfolios)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Descripción</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($portfolios as $portfolio): ?>
                                            <tr>
                                                <td><?= esc($portfolio['name']) ?></td>
                                                <td><?= esc($portfolio['description'] ?? 'N/A') ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $portfolio['status'] == 'active' ? 'success' : 'danger' ?>">
                                                        <?= $portfolio['status'] == 'active' ? 'Activo' : 'Inactivo' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="<?= site_url('portfolios/' . $portfolio['uuid']) ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i> Ver
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No hay carteras asignadas a este usuario.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
