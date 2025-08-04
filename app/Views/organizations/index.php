<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= $title ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php if ($isOrgSelector): ?>
    <!-- Organization Selector View -->
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-5">
                <h1 class="display-5 fw-bold text-primary">¡Bienvenido, Superadministrador!</h1>
                <p class="lead">Selecciona la organización sobre la que quieres trabajar para acceder al panel de administración.</p>
            </div>

            <?php if (session()->has('info')): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    <?= session('info') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <?php if (empty($organizations)): ?>
                    <div class="col-12">
                        <div class="card text-center">
                            <div class="card-body py-5">
                                <i class="bi bi-building display-1 text-muted mb-3"></i>
                                <h3>No hay organizaciones activas</h3>
                                <p class="text-muted">Crea una nueva organización para comenzar.</p>
                                <a href="<?= site_url('organizations/create') ?>" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Crear Primera Organización
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($organizations as $org): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 organization-card">
                                <div class="card-body d-flex flex-column">
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="organization-icon me-3">
                                                <i class="bi bi-building fs-2 text-primary"></i>
                                            </div>
                                            <div>
                                                <h5 class="card-title mb-1"><?= esc($org['name']) ?></h5>
                                                <span class="badge bg-success">Activa</span>
                                            </div>
                                        </div>
                                        <?php if ($org['description']): ?>
                                            <p class="card-text text-muted"><?= esc($org['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-auto">
                                        <a href="<?= site_url('organizations/select/' . $org['id']) ?>" 
                                           class="btn btn-primary btn-lg w-100">
                                            <i class="bi bi-arrow-right-circle me-2"></i>
                                            Trabajar en esta organización
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Admin Actions -->
            <div class="text-center mt-5">
                <div class="border-top pt-4">
                    <h6 class="text-muted mb-3">Acciones de Administración</h6>
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a href="<?= site_url('organizations/create') ?>" class="btn btn-outline-primary">
                            <i class="bi bi-plus-circle"></i> Nueva Organización
                        </a>
                        <?php if (!empty($organizations)): ?>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#manageModal">
                                <i class="bi bi-gear"></i> Gestionar Organizaciones
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Management Modal -->
    <div class="modal fade" id="manageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gestión de Organizaciones</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Get all organizations including inactive ones for management
                                $allOrgs = model('OrganizationModel')->findAll();
                                foreach ($allOrgs as $org): 
                                ?>
                                    <tr>
                                        <td><?= esc($org['name']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $org['status'] === 'active' ? 'success' : 'danger' ?>">
                                                <?= ucfirst($org['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($org['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= site_url('organizations/' . $org['uuid']) ?>" 
                                                   class="btn btn-outline-info btn-sm">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="<?= site_url('organizations/' . $org['uuid'] . '/edit') ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Traditional Management View -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1>Gestión de Organizaciones</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Organizaciones</li>
                </ol>
            </nav>
        </div>
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
                            <?php 
                            // Show all organizations for management view
                            $allOrgs = model('OrganizationModel')->findAll();
                            foreach ($allOrgs as $org): 
                            ?>
                                <tr>
                                    <td><?= $org['id'] ?></td>
                                    <td><?= esc($org['name']) ?></td>
                                    <td><?= esc($org['description']) ?: 'N/A' ?></td>
                                    <td>
                                        <span class="badge bg-<?= $org['status'] === 'active' ? 'success' : 'danger' ?>">
                                            <?= ucfirst($org['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($org['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="<?= site_url('organizations/' . $org['uuid']) ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i> Ver
                                            </a>
                                            <a href="<?= site_url('organizations/' . $org['uuid'] . '/edit') ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i> Editar
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $org['id'] ?>" data-name="<?= esc($org['name']) ?>">
                                                <i class="bi bi-trash"></i> Eliminar
                                            </button>
                                        </div>
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
<?php endif; ?>

<style>
.organization-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: 2px solid transparent;
}

.organization-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border-color: var(--bs-primary);
}

.organization-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(13, 110, 253, 0.1);
    border-radius: 12px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            
            deleteModal.querySelector('#orgName').textContent = name;
            deleteModal.querySelector('#deleteForm').action = '<?= site_url('organizations/') ?>' + id + '/delete';
        });
    }
});
</script>
<?= $this->endSection() ?>