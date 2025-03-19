<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?> - Sistema de Autenticación</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            padding-top: 70px;
            padding-bottom: 30px;
        }
    </style>
</head>
<body>
    <?php if (session()->get('isLoggedIn')): ?>
        <!-- Navbar para usuarios autenticados -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
            <div class="container">
                <a class="navbar-brand" href="<?= site_url('dashboard') ?>">Sistema</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="<?= site_url('dashboard') ?>">Dashboard</a>
                        </li>
                        
                        <?php $user = session()->get('user'); ?>
                        
                        <?php if ($user['role'] === 'superadmin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= site_url('organizations') ?>">Organizaciones</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($user['role'] === 'superadmin' || $user['role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= site_url('users') ?>">Usuarios</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <?= $user['name'] ?> (<?= ucfirst($user['role']) ?>)
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= site_url('users/profile') ?>">Mi Perfil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= site_url('auth/logout') ?>">Cerrar Sesión</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <div class="container mt-4">
        <!-- Mensajes Flash -->
        <?php if (session()->has('message')): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= session('message') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (session()->has('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= session('error') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (session()->has('errors')): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0">
                    <?php foreach (session('errors') as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?= $this->renderSection('content') ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>