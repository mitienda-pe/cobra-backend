<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Carteras de Cobro<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Carteras de Cobro</h1>
    <div>
        
        <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
            <a href="<?= site_url('portfolios/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nueva Cartera
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <?php if ($auth->hasRole('superadmin')): ?>
                        <th>Organización</th>
                        <?php endif; ?>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Clientes</th>
                        <th>Cobradores</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($portfolios)): ?>
                        <tr>
                            <td colspan="<?= $auth->hasRole('superadmin') ? '8' : '7' ?>" class="text-center">No hay carteras registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($portfolios as $portfolio): ?>
                            <tr>
                                <td><?= $portfolio['uuid'] ?></td>
                                <td><?= $portfolio['name'] ?></td>
                                <?php if ($auth->hasRole('superadmin')): ?>
                                <td>
                                    <?php 
                                        $orgId = $portfolio['organization_id'];
                                        echo isset($organizations[$orgId]) ? $organizations[$orgId]['name'] : 'N/A'; 
                                    ?>
                                </td>
                                <?php endif; ?>
                                <td><?= $portfolio['description'] ?? 'N/A' ?></td>
                                <td>
                                    <?php if ($portfolio['status'] == 'active'): ?>
                                        <span class="badge bg-success">Activa</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $db = \Config\Database::connect();
                                    $count = $db->table('client_portfolio')
                                               ->where('portfolio_uuid', $portfolio['uuid'])
                                               ->countAllResults();
                                    echo $count;
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $userCount = $db->table('portfolio_user')
                                                   ->where('portfolio_uuid', $portfolio['uuid'])
                                                   ->countAllResults();
                                    echo $userCount;
                                    ?>
                                </td>
                                <td>
                                    <a href="<?= site_url('portfolios/' . $portfolio['uuid']) ?>" class="btn btn-sm btn-info">Ver</a>
                                    <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
                                        <a href="<?= site_url('portfolios/' . $portfolio['uuid'] . '/edit') ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <a href="<?= site_url('portfolios/' . $portfolio['uuid'] . '/delete') ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro de eliminar esta cartera?')">Eliminar</a>
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