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

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Información del Cliente</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <dl class="row">
                    <dt class="col-sm-4">UUID</dt>
                    <dd class="col-sm-8"><?= $client['uuid'] ?></dd>

                    <dt class="col-sm-4">Razón Social</dt>
                    <dd class="col-sm-8"><?= $client['business_name'] ?></dd>

                    <dt class="col-sm-4">Nombre Legal</dt>
                    <dd class="col-sm-8"><?= $client['legal_name'] ?></dd>

                    <dt class="col-sm-4">RUC/DNI</dt>
                    <dd class="col-sm-8"><?= $client['document_number'] ?></dd>

                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8">
                        <?php if ($client['status'] == 'active'): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactivo</span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
            <div class="col-md-6">
                <dl class="row">
                    <dt class="col-sm-4">Contacto</dt>
                    <dd class="col-sm-8"><?= $client['contact_name'] ?: 'No especificado' ?></dd>

                    <dt class="col-sm-4">Teléfono</dt>
                    <dd class="col-sm-8"><?= $client['contact_phone'] ?: 'No especificado' ?></dd>

                    <dt class="col-sm-4">Email</dt>
                    <dd class="col-sm-8"><?= $client['email'] ?: 'No especificado' ?></dd>

                    <dt class="col-sm-4">Dirección</dt>
                    <dd class="col-sm-8"><?= $client['address'] ?: 'No especificada' ?></dd>

                    <dt class="col-sm-4">Ubigeo</dt>
                    <dd class="col-sm-8"><?= $client['ubigeo'] ?: 'No especificado' ?></dd>

                    <dt class="col-sm-4">Código Postal</dt>
                    <dd class="col-sm-8"><?= $client['zip_code'] ?: 'No especificado' ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Carteras Asignadas</h5>
    </div>
    <div class="card-body">
        <?php if (empty($portfolios)): ?>
            <p class="text-muted">Este cliente no está asignado a ninguna cartera.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>UUID</th>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($portfolios as $portfolio): ?>
                            <tr>
                                <td><?= $portfolio['uuid'] ?></td>
                                <td><?= $portfolio['name'] ?></td>
                                <td>
                                    <?php if ($portfolio['status'] == 'active'): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= site_url('portfolios/' . $portfolio['uuid']) ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Información de la Organización</h5>
    </div>
    <div class="card-body">
        <dl class="row">
            <dt class="col-sm-3">Organización</dt>
            <dd class="col-sm-9"><?= $organization['name'] ?></dd>

            <dt class="col-sm-3">Código</dt>
            <dd class="col-sm-9"><?= $organization['code'] ?></dd>

            <dt class="col-sm-3">Estado</dt>
            <dd class="col-sm-9">
                <?php if ($organization['status'] == 'active'): ?>
                    <span class="badge bg-success">Activo</span>
                <?php else: ?>
                    <span class="badge bg-danger">Inactivo</span>
                <?php endif; ?>
            </dd>

            <?php if ($organization['description']): ?>
            <dt class="col-sm-3">Descripción</dt>
            <dd class="col-sm-9"><?= $organization['description'] ?></dd>
            <?php endif; ?>
        </dl>
    </div>
</div>
<?php endif; ?>

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