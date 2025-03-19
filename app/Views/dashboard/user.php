<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Dashboard Usuario<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <h1>Dashboard de Usuario</h1>
        <p>Bienvenido, <?= $user['name'] ?>!</p>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6 mx-auto">
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