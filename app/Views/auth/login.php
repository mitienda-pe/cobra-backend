<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Iniciar Sesión<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Iniciar Sesión</h3>
            </div>
            <div class="card-body">
                <form action="<?= site_url('auth/login') ?>" method="post">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= old('email') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Recordarme</label>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
                    </div>
                </form>
                <div class="mt-3 text-center">
                    <a href="<?= site_url('auth/forgot-password') ?>">¿Olvidó su contraseña?</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>