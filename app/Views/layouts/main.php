<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?> - Sistema de Cobranzas</title>
    <?= csrf_meta() ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            padding-top: 70px;
            padding-bottom: 30px;
        }

        /* Organization indicator styles - only for superadmin */
        <?php
        $user = session()->get('user');
        $selectedOrgId = session()->get('selected_organization_id');
        $isSuperadmin = isset($user) && $user['role'] === 'superadmin';

        if ($selectedOrgId && $isSuperadmin):
        ?>body {
            border-top: 5px solid #007bff;
        }

        .org-indicator {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: #007bff;
            color: white;
            text-align: center;
            font-size: 0.8rem;
            padding: 1px 0;
            z-index: 1100;
        }

        <?php endif; ?>
    </style>
</head>

<body>
    <?php
    // Display organization indicator for superadmins only
    $user = session()->get('user');
    $isSuperadmin = isset($user) && $user['role'] === 'superadmin';
    $selectedOrgId = session()->get('selected_organization_id');

    if ($selectedOrgId && $isSuperadmin):
        $selectedOrgName = null;
        $orgModel = new \App\Models\OrganizationModel();
        $org = $orgModel->find($selectedOrgId);
        if ($org) {
            $selectedOrgName = $org['name'];
        }
    ?>
        <div class="org-indicator">
            <strong>Organización activa:</strong> <?= esc($selectedOrgName) ?>
        </div>
    <?php endif; ?>

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

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="cobrosDropdown" role="button" data-bs-toggle="dropdown">
                                Gestión de Cobranzas
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= site_url('clients') ?>">Clientes</a></li>
                                <li><a class="dropdown-item" href="<?= site_url('portfolios') ?>">Carteras de Cobro</a></li>
                                <li><a class="dropdown-item" href="<?= site_url('invoices') ?>">Facturas</a></li>
                                <li><a class="dropdown-item" href="<?= site_url('payments') ?>">Pagos</a></li>
                                <li><a class="dropdown-item" href="<?= site_url('payments/report') ?>">Reportes</a></li>
                                <?php if ($user['role'] === 'superadmin' || $user['role'] === 'admin'): ?>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="<?= site_url('webhooks') ?>">Webhooks</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    </ul>
                    <ul class="navbar-nav ms-auto">
                        <?php if ($user['role'] === 'superadmin'): ?>
                            <?php
                            // Obtener información de la organización seleccionada
                            $selectedOrgId = session()->get('selected_organization_id');
                            $selectedOrgName = 'Todas las organizaciones';

                            if ($selectedOrgId) {
                                // Cargar modelo para obtener el nombre 
                                $orgModel = new \App\Models\OrganizationModel();
                                $org = $orgModel->find($selectedOrgId);
                                if ($org) {
                                    $selectedOrgName = $org['name'];
                                }
                            }
                            ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="orgDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-building"></i> <?= esc($selectedOrgName) ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item <?= !$selectedOrgId ? 'active' : '' ?>" href="<?= base_url(uri_string()) . '?clear_org=1' ?>">Todas las organizaciones</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <?php
                                    // Cargar todas las organizaciones para el dropdown
                                    $orgModel = new \App\Models\OrganizationModel();
                                    $allOrgs = $orgModel->findAll();
                                    foreach ($allOrgs as $org):
                                    ?>
                                        <li>
                                            <a class="dropdown-item <?= $selectedOrgId == $org['id'] ? 'active' : '' ?>"
                                                href="<?= base_url(uri_string()) . '?org_id=' . $org['id'] ?>">
                                                <?= esc($org['name']) ?> <?= $selectedOrgId == $org['id'] ? '(Seleccionada)' : '' ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php
                                    // Debug information to help troubleshoot
                                    log_message('debug', '[main.layout] Menu rendering with selectedOrgId: ' .
                                        ($selectedOrgId ? $selectedOrgId : 'null'));
                                    ?>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <?= $user['name'] ?> (<?= ucfirst($user['role']) ?>)
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= site_url('users/profile') ?>">Mi Perfil</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
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
    <script src="<?= base_url('js/csrf-handler.js') ?>"></script>

    <!-- Render section for page-specific scripts -->
    <?= $this->renderSection('scripts') ?>
</body>

</html>