<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Cliente: <?= $client['business_name'] ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= esc($client['business_name']) ?></h1>
    <div>
        <?php if ($auth->hasRole('superadmin') || ($auth->hasRole('admin') && $auth->organizationId() == $client['organization_id'])): ?>
            <a href="<?= site_url('clients/' . $client['uuid'] . '/edit') ?>" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Editar
            </a>
        <?php endif; ?>
        <a href="<?= site_url('clients') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<?= view('partials/_alerts') ?>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Información del Cliente</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>RUC:</strong> <?= esc($client['document_number']) ?></p>
                        <p><strong>Razón Social:</strong> <?= esc($client['legal_name']) ?></p>
                        <p><strong>Nombre Comercial:</strong> <?= esc($client['business_name']) ?></p>
                        <?php if (isset($client['external_id']) && !empty($client['external_id'])): ?>
                            <p><strong>ID Externo:</strong> <?= esc($client['external_id']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Estado:</strong> 
                            <span class="badge bg-<?= $client['status'] == 'active' ? 'success' : 'danger' ?>">
                                <?= $client['status'] == 'active' ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </p>
                        <p><strong>Organización:</strong> <?= esc($organization['name']) ?></p>
                        <p><strong>Fecha de Creación:</strong> <?= date('d/m/Y', strtotime($client['created_at'])) ?></p>
                        <?php if (isset($client['contact_name']) && !empty($client['contact_name'])): ?>
                            <p><strong>Contacto:</strong> <?= esc($client['contact_name']) ?></p>
                        <?php endif; ?>
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
                    <h5 class="mb-0">Carteras Asignadas</h5>
                    <?php if ($auth->hasRole('superadmin') || ($auth->hasRole('admin') && $auth->organizationId() == $client['organization_id'])): ?>
                        <a href="<?= site_url('portfolios/create') ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle"></i> Nueva Cartera
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($portfolios)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
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
                                        <td><?= esc($portfolio['description']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $portfolio['status'] == 'active' ? 'success' : 'danger' ?>">
                                                <?= $portfolio['status'] == 'active' ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="<?= site_url('portfolios/' . $portfolio['uuid']) ?>" class="btn btn-info btn-sm" title="Ver">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($auth->hasRole('superadmin') || ($auth->hasRole('admin') && $auth->organizationId() == $client['organization_id'])): ?>
                                                    <a href="<?= site_url('portfolios/' . $portfolio['uuid'] . '/edit') ?>" class="btn btn-primary btn-sm" title="Editar">
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
                        <i class="bi bi-info-circle"></i> Este cliente no tiene carteras asignadas.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(elementId) {
    const copyText = document.getElementById(elementId);
    copyText.select();
    copyText.setSelectionRange(0, 99999); // For mobile devices
    navigator.clipboard.writeText(copyText.value);
    
    // Create a tooltip to show "Copied!"
    const button = document.querySelector(`#${elementId} + button`);
    const originalHtml = button.innerHTML;
    button.innerHTML = '<i class="bi bi-check"></i>';
    button.classList.add('btn-success');
    button.classList.remove('btn-outline-secondary');
    
    // Restore original after 2 seconds
    setTimeout(() => {
        button.innerHTML = originalHtml;
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-secondary');
    }, 2000);
}
</script>
<?= $this->endSection() ?>