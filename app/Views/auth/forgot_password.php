<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Recuperar Contraseña<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Recuperar Contraseña</h3>
            </div>
            <div class="card-body">
                <p>Ingrese su correo electrónico y le enviaremos un enlace para restablecer su contraseña.</p>
                <form action="<?= site_url('auth/forgot-password') ?>" method="post">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= old('email') ?>" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Enviar Enlace</button>
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