<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
<?= $title ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Backoffice Ligo</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-wallet fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Balance de Cuenta</h5>
                                    <p class="card-text">Consulta el saldo actual de tu cuenta CCI</p>
                                    <a href="<?= base_url('backoffice/balance') ?>" class="btn btn-primary">Ver Balance</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-list fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Transacciones</h5>
                                    <p class="card-text">Lista las transacciones por rango de fechas</p>
                                    <a href="<?= base_url('backoffice/transactions') ?>" class="btn btn-success">Ver Transacciones</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-plus-circle fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Recargas</h5>
                                    <p class="card-text">Consulta el historial de recargas</p>
                                    <a href="<?= base_url('backoffice/recharges') ?>" class="btn btn-info">Ver Recargas</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-exchange-alt fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Transferencias</h5>
                                    <p class="card-text">Realiza transferencias ordinarias</p>
                                    <a href="<?= base_url('backoffice/transfer') ?>" class="btn btn-warning">Nueva Transferencia</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php 
                    // Only show configuration section for superadmin
                    $user = session()->get('user');
                    $isSuperadmin = isset($user) && $user['role'] === 'superadmin';
                    if ($isSuperadmin): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <h4><i class="bi bi-gear-wide-connected"></i> Configuración del Sistema</h4>
                            <hr>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="bi bi-gear-wide-connected fa-3x text-danger mb-3"></i>
                                    <h5 class="card-title">Configuración Ligo</h5>
                                    <p class="card-text">Administra las credenciales centralizadas de Ligo</p>
                                    <a href="<?= site_url('superadmin/ligo-config') ?>" class="btn btn-danger">
                                        <i class="bi bi-gear-wide-connected"></i> Configurar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>