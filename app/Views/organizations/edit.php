<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Organización<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Editar Organización</h3>
                    <a href="<?= site_url('organizations') ?>" class="btn btn-secondary">Volver</a>
                </div>
            </div>
            <div class="card-body">
                <form action="<?= site_url('organizations/' . $organization['id']) ?>" method="post">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre</label>
                        <input type="text" class="form-control <?= session('errors.name') ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= old('name', $organization['name']) ?>" required>
                        <?php if (session('errors.name')): ?>
                            <div class="invalid-feedback"><?= session('errors.name') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="code" class="form-label">Código</label>
                        <input type="text" class="form-control <?= session('errors.code') ? 'is-invalid' : '' ?>" id="code" name="code" value="<?= old('code', $organization['code']) ?>" readonly>
                        <div class="form-text">El código no se puede modificar una vez creado.</div>
                        <?php if (session('errors.code')): ?>
                            <div class="invalid-feedback"><?= session('errors.code') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea class="form-control <?= session('errors.description') ? 'is-invalid' : '' ?>" id="description" name="description" rows="3"><?= old('description', $organization['description']) ?></textarea>
                        <?php if (session('errors.description')): ?>
                            <div class="invalid-feedback"><?= session('errors.description') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Estado</label>
                        <select class="form-select <?= session('errors.status') ? 'is-invalid' : '' ?>" id="status" name="status" required>
                            <option value="active" <?= old('status', $organization['status']) == 'active' ? 'selected' : '' ?>>Activo</option>
                            <option value="inactive" <?= old('status', $organization['status']) == 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                        <?php if (session('errors.status')): ?>
                            <div class="invalid-feedback"><?= session('errors.status') ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>