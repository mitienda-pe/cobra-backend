<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Mi Perfil<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Mi Perfil</h3>
            </div>
            <div class="card-body">
                <form action="<?= site_url('users/profile') ?>" method="post">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= old('name', $user['name']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= old('email', $user['email']) ?>" required>
                    </div>
                    
                    <hr class="my-4">
                    <h5>Cambiar Contraseña</h5>
                    <p class="text-muted mb-3">Deje los campos en blanco si no desea cambiar su contraseña.</p>
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Contraseña Actual</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>
                    
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirmar Nueva Contraseña</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Actualizar Perfil</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3 class="mb-0">Información de la Cuenta</h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <th style="width: 200px;">Rol:</th>
                        <td>
                            <?php if ($user['role'] == 'superadmin'): ?>
                                <span class="badge bg-danger">Superadmin</span>
                            <?php elseif ($user['role'] == 'admin'): ?>
                                <span class="badge bg-primary">Administrador</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Usuario</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Estado:</th>
                        <td>
                            <?php if ($user['status'] == 'active'): ?>
                                <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactivo</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($user['organization_id']): ?>
                        <tr>
                            <th>Organización:</th>
                            <td>
                                <?php 
                                // En una implementación real, buscarías el nombre de la organización en la base de datos
                                echo "ID: " . $user['organization_id']; 
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Fecha de Registro:</th>
                        <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <th>Última Actualización:</th>
                        <td><?= date('d/m/Y H:i', strtotime($user['updated_at'])) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>