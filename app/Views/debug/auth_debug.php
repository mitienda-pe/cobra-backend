<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Debug de Autenticación<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Información de Depuración</h3>
                    <a href="<?= site_url('portfolios') ?>" class="btn btn-secondary">Volver</a>
                </div>
            </div>
            <div class="card-body">
                <h4>Información de Usuario Actual</h4>
                <table class="table table-bordered">
                    <tr>
                        <th>ID</th>
                        <td><?= $user['id'] ?? 'No disponible' ?></td>
                    </tr>
                    <tr>
                        <th>Nombre</th>
                        <td><?= $user['name'] ?? 'No disponible' ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?= $user['email'] ?? 'No disponible' ?></td>
                    </tr>
                    <tr>
                        <th>Rol</th>
                        <td><?= $user['role'] ?? 'No disponible' ?></td>
                    </tr>
                    <tr>
                        <th>ID de Organización</th>
                        <td><?= $user['organization_id'] ?? 'No disponible' ?></td>
                    </tr>
                </table>
                
                <h4 class="mt-4">Información CSRF</h4>
                <table class="table table-bordered">
                    <tr>
                        <th>CSRF Token Name</th>
                        <td><?= csrf_token() ?></td>
                    </tr>
                    <tr>
                        <th>CSRF Token Value</th>
                        <td><?= csrf_hash() ?></td>
                    </tr>
                </table>
                
                <h4 class="mt-4">Organizaciones</h4>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Creado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($organizations as $org): ?>
                            <tr>
                                <td><?= $org['id'] ?></td>
                                <td><?= $org['name'] ?></td>
                                <td><?= $org['status'] ?></td>
                                <td><?= $org['created_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <h4 class="mt-4">Usuarios</h4>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Organización ID</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= $u['name'] ?></td>
                                <td><?= $u['email'] ?></td>
                                <td><?= $u['role'] ?></td>
                                <td><?= $u['organization_id'] ?></td>
                                <td><?= $u['status'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <h4 class="mt-4">Carteras</h4>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Organización ID</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($portfolios as $p): ?>
                            <tr>
                                <td><?= $p['id'] ?></td>
                                <td><?= $p['name'] ?></td>
                                <td><?= $p['organization_id'] ?></td>
                                <td><?= $p['status'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>