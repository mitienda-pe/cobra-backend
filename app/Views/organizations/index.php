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
                        <th>ID</th>
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
                                <td><?= $org['id'] ?></td>
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
                                    <a href="<?= site_url('organizations/view/' . $org['id']) ?>" class="btn btn-sm btn-info">Ver</a>
                                    <a href="<?= site_url('organizations/edit/' . $org['id']) ?>" class="btn btn-sm btn-primary">Editar</a>
                                    <a href="<?= site_url('organizations/delete/' . $org['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro de eliminar esta organización?')">Eliminar</a>
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