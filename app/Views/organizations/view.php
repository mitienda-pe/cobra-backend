<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Ver Organización<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= esc($organization['name']) ?></h1>
    <div>
        <?php if ($auth->hasRole('superadmin')): ?>
            <a href="<?= site_url('organizations/' . $organization['uuid'] . '/edit') ?>" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Editar
            </a>
        <?php endif; ?>
        <a href="<?= site_url('organizations') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Información General</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Nombre:</strong> <?= esc($organization['name']) ?></p>
                        <?php if (isset($organization['code']) && !empty($organization['code'])): ?>
                            <p><strong>Código:</strong> <?= esc($organization['code']) ?></p>
                        <?php endif; ?>
                        <?php if (isset($organization['description']) && !empty($organization['description'])): ?>
                            <p><strong>Descripción:</strong> <?= esc($organization['description']) ?></p>
                        <?php endif; ?>
                        <?php if ($auth->hasRole('superadmin')): ?>
                            <p><strong>UUID:</strong> <code><?= esc($organization['uuid']) ?></code></p>
                            <p><strong>ID:</strong> <code><?= esc($organization['id']) ?></code></p>
                            <p>
                                <a href="<?= site_url('debug/ligo-uuid/status?uuid=' . $organization['uuid']) ?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="bi bi-bug"></i> Diagnosticar Ligo
                                </a>
                                <a href="<?= site_url('debug/ligo-uuid/enable?uuid=' . $organization['uuid']) ?>" target="_blank" class="btn btn-sm btn-warning">
                                    <i class="bi bi-toggle-on"></i> Habilitar Ligo
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Estado:</strong> 
                            <span class="badge bg-<?= $organization['status'] == 'active' ? 'success' : 'danger' ?>">
                                <?= $organization['status'] == 'active' ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </p>
                        <p><strong>Fecha de Creación:</strong> <?= date('d/m/Y', strtotime($organization['created_at'])) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Usuarios</h5>
                    <?php if ($auth->hasRole('superadmin')): ?>
                        <a href="<?= site_url('users/create') ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle"></i> Nuevo Usuario
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($users)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= esc($user['name']) ?></td>
                                        <td><?= esc($user['email']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $user['status'] == 'active' ? 'success' : 'danger' ?>">
                                                <?= $user['status'] == 'active' ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="<?= site_url('users/' . $user['uuid']) ?>" class="btn btn-info btn-sm" title="Ver">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($auth->hasRole('superadmin')): ?>
                                                    <a href="<?= site_url('users/' . $user['uuid'] . '/edit') ?>" class="btn btn-primary btn-sm" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No hay usuarios registrados en esta organización.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
<?= $this->endSection() ?>