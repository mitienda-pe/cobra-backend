<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Restablecer Contraseña<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Restablecer Contraseña</h3>
            </div>
            <div class="card-body">
                <form action="<?= site_url('auth/reset-password/' . $token) ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmar Contraseña</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Restablecer Contraseña</button>
                    </div>
                </form>
                <div class="mt-3 text-center">
                    <a href="<?= site_url('auth/login') ?>">Volver a Iniciar Sesión</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>