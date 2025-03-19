<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Dashboard Admin<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <h1>Dashboard de Administrador</h1>
        <p>Bienvenido, <?= $user['name'] ?>!</p>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Gestión de Usuarios</h5>
                <p class="card-text">Administre los usuarios de su organización.</p>
                <a href="<?= site_url('users') ?>" class="btn btn-primary">Ir a Usuarios</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Mi Perfil</h5>
                <p class="card-text">Actualice su información personal.</p>
                <a href="<?= site_url('users/profile') ?>" class="btn btn-primary">Ir a Mi Perfil</a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>