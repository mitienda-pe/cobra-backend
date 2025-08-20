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
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
            --notion-bg: #ffffff;
            --notion-sidebar: #ffffff;
            --notion-text: #37352f;
            --notion-light-text: #6b6b6b;
            --notion-border: #e0e0e0;
            --notion-hover: #efefef;
            --notion-active: #e9e9e9;
            --notion-blue: #0a85d1;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, sans-serif;
            color: var(--notion-text);
            background-color: var(--notion-bg);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* Sidebar styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background-color: var(--notion-sidebar);
            border-right: 1px solid var(--notion-border);
            overflow-y: auto;
            transition: width 0.3s ease;
            z-index: 1000;
        }

        .sidebar-collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            padding: 16px;
            border-bottom: 1px solid var(--notion-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-brand {
            font-weight: 600;
            font-size: 1.2rem;
            color: var(--notion-text);
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: var(--notion-light-text);
            cursor: pointer;
            padding: 4px;
        }

        .sidebar-menu {
            padding: 8px 0;
        }

        .sidebar-item {
            padding: 8px 16px;
            display: flex;
            align-items: center;
            color: var(--notion-text);
            text-decoration: none;
            border-radius: 4px;
            margin: 2px 8px;
            transition: background-color 0.2s;
        }

        .sidebar-item:hover {
            background-color: var(--notion-hover);
        }

        .sidebar-item.active {
            background-color: var(--notion-active);
            font-weight: 500;
        }

        .sidebar-item i {
            margin-right: 12px;
            font-size: 1.1rem;
            min-width: 20px;
            text-align: center;
        }

        .sidebar-item-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-dropdown {
            margin: 2px 8px;
        }

        .sidebar-dropdown-toggle {
            padding: 8px 16px;
            display: flex;
            align-items: center;
            color: var(--notion-text);
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .sidebar-dropdown-toggle:hover {
            background-color: var(--notion-hover);
        }

        .sidebar-dropdown-toggle i.menu-icon {
            margin-right: 12px;
            font-size: 1.1rem;
            min-width: 20px;
            text-align: center;
        }

        .sidebar-dropdown-toggle i.dropdown-icon {
            margin-left: auto;
            transition: transform 0.3s;
        }

        .sidebar-dropdown-menu {
            margin-left: 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .sidebar-dropdown.show .sidebar-dropdown-menu {
            max-height: 500px;
        }

        .sidebar-dropdown.show .dropdown-icon {
            transform: rotate(90deg);
        }

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content-expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Organization indicator styles - only for superadmin */
        <?php
        $user = session()->get('user');
        $selectedOrgId = session()->get('selected_organization_id');
        $isSuperadmin = isset($user) && $user['role'] === 'superadmin';

        if ($selectedOrgId && $isSuperadmin):
        ?>.sidebar {
            border-top: 5px solid var(--notion-blue);
        }

        .org-indicator {
            background-color: var(--notion-blue);
            color: white;
            text-align: center;
            font-size: 0.8rem;
            padding: 4px 0;
            width: 100%;
        }

        <?php endif; ?>

        /* Menu divider */
        .menu-divider {
            height: 1px;
            background-color: var(--notion-border);
            margin: 8px 16px;
        }

        /* Sidebar notice */
        .sidebar-notice {
            margin: 16px 8px;
            padding: 16px;
            background-color: rgba(13, 110, 253, 0.05);
            border-radius: 6px;
            border: 1px solid rgba(13, 110, 253, 0.1);
        }

        .sidebar-notice small {
            line-height: 1.4;
            display: block;
        }

        /* User profile section at bottom of sidebar */
        .sidebar-footer {
            position: sticky;
            bottom: 0;
            background-color: var(--notion-sidebar);
            border-top: 1px solid var(--notion-border);
            padding: 12px 16px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            cursor: pointer;
            border-radius: 4px;
            padding: 6px;
        }

        .user-profile:hover {
            background-color: var(--notion-hover);
        }

        .user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 4px;
            background-color: var(--notion-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 10px;
        }

        .user-info {
            flex-grow: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-name {
            font-size: 0.9rem;
            font-weight: 500;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--notion-light-text);
        }

        .user-dropdown {
            display: none;
            position: absolute;
            bottom: 60px;
            left: 16px;
            right: 16px;
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            z-index: 1001;
        }

        .user-dropdown.show {
            display: block;
        }

        .user-dropdown-item {
            padding: 8px 12px;
            display: block;
            color: var(--notion-text);
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .user-dropdown-item:hover {
            background-color: var(--notion-hover);
        }

        .user-dropdown-divider {
            height: 1px;
            background-color: var(--notion-border);
            margin: 4px 0;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }

            .sidebar.mobile-show {
                width: var(--sidebar-width);
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }

            .mobile-overlay.show {
                display: block;
            }

            .mobile-toggle {
                display: block;
                position: fixed;
                top: 10px;
                left: 10px;
                z-index: 1002;
                background-color: white;
                border: 1px solid var(--notion-border);
                border-radius: 4px;
                padding: 8px;
                cursor: pointer;
            }
        }

        .sidebar-section {
            padding: 8px 0;
            border-bottom: 1px solid var(--notion-border);
        }

        .sidebar-section-header {
            padding: 8px 16px;
            font-weight: 500;
            color: var(--notion-text);
        }
    </style>
</head>

<body>
    <?php if (session()->get('isLoggedIn')): ?>
        <?php
        // Get user information
        $user = session()->get('user');
        $isSuperadmin = isset($user) && $user['role'] === 'superadmin';
        $isAdmin = isset($user) && ($user['role'] === 'admin' || $user['role'] === 'superadmin');
        $selectedOrgId = session()->get('selected_organization_id');
        $selectedOrgUuid = null;
        $selectedOrgName = 'Todas las organizaciones';

        if ($selectedOrgId && $isSuperadmin) {
            $orgModel = new \App\Models\OrganizationModel();
            $org = $orgModel->find($selectedOrgId);
            if ($org) {
                $selectedOrgName = $org['name'];
                $selectedOrgUuid = $org['uuid']; // Get UUID for secure URLs
            }
        }

        // Get current URL for active menu highlighting
        $currentUrl = current_url(true);
        $currentPath = $currentUrl->getPath();
        ?>

        <!-- Mobile sidebar toggle button -->
        <div class="mobile-toggle d-md-none">
            <i class="bi bi-list"></i>
        </div>

        <!-- Mobile overlay -->
        <div class="mobile-overlay"></div>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar header -->
            <div class="sidebar-header">
                <a href="<?= site_url('dashboard') ?>" class="sidebar-brand">CobraPepe</a>
                <button class="sidebar-toggle">
                    <i class="bi bi-chevron-left"></i>
                </button>
            </div>

            <!-- Organization Context Display -->
            <?php if ($isSuperadmin && $selectedOrgId): ?>
                <div class="org-context-bar">
                    <div class="d-flex align-items-center justify-content-between p-3 bg-light border-bottom">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-building text-primary me-2"></i>
                            <div>
                                <small class="text-muted d-block">Trabajando en:</small>
                                <strong class="text-dark"><?= esc($selectedOrgName) ?></strong>
                            </div>
                        </div>
                        <a href="<?= site_url('organizations') ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-arrow-left-right"></i>
                            <span class="sidebar-item-text">Cambiar</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Sidebar menu -->
            <div class="sidebar-menu">
                <?php if ($isSuperadmin): ?>
                    <!-- Superadmin dropdown for Organizations and Backoffice -->
                    <div class="sidebar-dropdown <?= (strpos($currentPath, '/organizations') === 0 || strpos($currentPath, '/backoffice') === 0) ? 'show' : '' ?>">
                        <div class="sidebar-dropdown-toggle">
                            <i class="bi bi-gear-wide-connected menu-icon"></i>
                            <span class="sidebar-item-text">Administración</span>
                            <i class="bi bi-chevron-right dropdown-icon"></i>
                        </div>
                        <div class="sidebar-dropdown-menu">
                            <a href="<?= site_url('organizations') ?>" class="sidebar-item <?= strpos($currentPath, '/organizations') === 0 ? 'active' : '' ?>">
                                <i class="bi bi-building"></i>
                                <span class="sidebar-item-text">Organizaciones</span>
                            </a>
                            <a href="<?= site_url('superadmin/ligo-config') ?>" class="sidebar-item <?= strpos($currentPath, '/superadmin/ligo-config') === 0 ? 'active' : '' ?>">
                                <i class="bi bi-gear-wide-connected"></i>
                                <span class="sidebar-item-text">Configuración Ligo</span>
                            </a>
                            <?php if ($selectedOrgId): ?>
                                <a href="<?= site_url('backoffice') ?>" class="sidebar-item <?= strpos($currentPath, '/backoffice') === 0 ? 'active' : '' ?>">
                                    <i class="bi bi-gear"></i>
                                    <span class="sidebar-item-text">Backoffice Ligo</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($selectedOrgId): ?>
                        <!-- Show full menu only when organization is selected -->
                        <div class="menu-divider"></div>
                        
                        <a href="<?= site_url('dashboard') ?>" class="sidebar-item <?= $currentPath === '/dashboard' ? 'active' : '' ?>">
                            <i class="bi bi-speedometer2"></i>
                            <span class="sidebar-item-text">Dashboard</span>
                        </a>

                        <a href="<?= site_url('users') ?>" class="sidebar-item <?= strpos($currentPath, '/users') === 0 ? 'active' : '' ?>">
                            <i class="bi bi-people"></i>
                            <span class="sidebar-item-text">Usuarios</span>
                        </a>

                        <a href="<?= site_url('clients') ?>" class="sidebar-item <?= $currentPath === '/clients' ? 'active' : '' ?>">
                            <i class="bi bi-person-vcard"></i>
                            <span class="sidebar-item-text">Clientes</span>
                        </a>

                        <a href="<?= site_url('portfolios') ?>" class="sidebar-item <?= $currentPath === '/portfolios' ? 'active' : '' ?>">
                            <i class="bi bi-folder"></i>
                            <span class="sidebar-item-text">Carteras</span>
                        </a>

                        <a href="<?= site_url('invoices') ?>" class="sidebar-item <?= $currentPath === '/invoices' ? 'active' : '' ?>">
                            <i class="bi bi-file-earmark-text"></i>
                            <span class="sidebar-item-text">Facturas</span>
                        </a>

                        <a href="<?= site_url('instalments') ?>" class="sidebar-item <?= $currentPath === '/instalments' ? 'active' : '' ?>">
                            <i class="bi bi-calendar-check"></i>
                            <span class="sidebar-item-text">Cuotas</span>
                        </a>

                        <a href="<?= site_url('payments') ?>" class="sidebar-item <?= $currentPath === '/payments' ? 'active' : '' ?>">
                            <i class="bi bi-credit-card"></i>
                            <span class="sidebar-item-text">Pagos</span>
                        </a>

                        <a href="<?= site_url('payments/report') ?>" class="sidebar-item <?= $currentPath === '/payments/report' ? 'active' : '' ?>">
                            <i class="bi bi-bar-chart"></i>
                            <span class="sidebar-item-text">Reportes</span>
                        </a>

                        <a href="<?= site_url('organizations/account/' . $selectedOrgUuid) ?>" class="sidebar-item <?= strpos($currentPath, '/organizations/account') === 0 ? 'active' : '' ?>">
                            <i class="bi bi-wallet2"></i>
                            <span class="sidebar-item-text">Estado de Cuenta</span>
                        </a>

                        <a href="<?= site_url('webhooks') ?>" class="sidebar-item <?= $currentPath === '/webhooks' ? 'active' : '' ?>">
                            <i class="bi bi-link-45deg"></i>
                            <span class="sidebar-item-text">Webhooks</span>
                        </a>
                    <?php else: ?>
                        <!-- Minimal menu when no organization selected -->
                        <div class="sidebar-notice">
                            <div class="text-center p-3">
                                <i class="bi bi-info-circle text-muted mb-2 d-block"></i>
                                <small class="text-muted">
                                    <span class="sidebar-item-text">Selecciona una organización para acceder a todos los módulos</span>
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- Full menu for admin users (always have organization context) -->
                    <a href="<?= site_url('dashboard') ?>" class="sidebar-item <?= $currentPath === '/dashboard' ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span class="sidebar-item-text">Dashboard</span>
                    </a>

                    <?php if ($isAdmin): ?>
                        <a href="<?= site_url('users') ?>" class="sidebar-item <?= strpos($currentPath, '/users') === 0 ? 'active' : '' ?>">
                            <i class="bi bi-people"></i>
                            <span class="sidebar-item-text">Usuarios</span>
                        </a>
                    <?php endif; ?>

                    <a href="<?= site_url('clients') ?>" class="sidebar-item <?= $currentPath === '/clients' ? 'active' : '' ?>">
                        <i class="bi bi-person-vcard"></i>
                        <span class="sidebar-item-text">Clientes</span>
                    </a>

                    <a href="<?= site_url('portfolios') ?>" class="sidebar-item <?= $currentPath === '/portfolios' ? 'active' : '' ?>">
                        <i class="bi bi-folder"></i>
                        <span class="sidebar-item-text">Carteras</span>
                    </a>

                    <a href="<?= site_url('invoices') ?>" class="sidebar-item <?= $currentPath === '/invoices' ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-text"></i>
                        <span class="sidebar-item-text">Facturas</span>
                    </a>

                    <a href="<?= site_url('instalments') ?>" class="sidebar-item <?= $currentPath === '/instalments' ? 'active' : '' ?>">
                        <i class="bi bi-calendar-check"></i>
                        <span class="sidebar-item-text">Cuotas</span>
                    </a>

                    <a href="<?= site_url('payments') ?>" class="sidebar-item <?= $currentPath === '/payments' ? 'active' : '' ?>">
                        <i class="bi bi-credit-card"></i>
                        <span class="sidebar-item-text">Pagos</span>
                    </a>

                    <a href="<?= site_url('payments/report') ?>" class="sidebar-item <?= $currentPath === '/payments/report' ? 'active' : '' ?>">
                        <i class="bi bi-bar-chart"></i>
                        <span class="sidebar-item-text">Reportes</span>
                    </a>

                    <?php if ($isAdmin): ?>
                        <a href="<?= site_url('webhooks') ?>" class="sidebar-item <?= $currentPath === '/webhooks' ? 'active' : '' ?>">
                            <i class="bi bi-link-45deg"></i>
                            <span class="sidebar-item-text">Webhooks</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

            </div>

            <!-- User profile section -->
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?= substr($user['name'], 0, 1) ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= $user['name'] ?></div>
                        <div class="user-role"><?= ucfirst($user['role']) ?></div>
                    </div>
                    <i class="bi bi-chevron-up ms-2"></i>
                </div>
                <div class="user-dropdown">
                    <a href="<?= site_url('users/profile') ?>" class="user-dropdown-item">
                        <i class="bi bi-person me-2"></i> Mi Perfil
                    </a>
                    <div class="user-dropdown-divider"></div>
                    <a href="<?= site_url('auth/logout') ?>" class="user-dropdown-item">
                        <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="main-content">
        <?php else: ?>
            <div class="container">
            <?php endif; ?>

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

            <!-- Contenido principal -->
            <?= $this->renderSection('content') ?>

            </div>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Sidebar toggle functionality
                    const sidebarToggle = document.querySelector('.sidebar-toggle');
                    const sidebar = document.querySelector('.sidebar');
                    const mainContent = document.querySelector('.main-content');

                    if (sidebarToggle) {
                        sidebarToggle.addEventListener('click', function() {
                            sidebar.classList.toggle('sidebar-collapsed');
                            mainContent.classList.toggle('main-content-expanded');

                            // Change toggle icon
                            const icon = this.querySelector('i');
                            if (sidebar.classList.contains('sidebar-collapsed')) {
                                icon.classList.replace('bi-chevron-left', 'bi-chevron-right');
                            } else {
                                icon.classList.replace('bi-chevron-right', 'bi-chevron-left');
                            }
                        });
                    }

                    // Dropdown functionality
                    const dropdownToggles = document.querySelectorAll('.sidebar-dropdown-toggle');
                    dropdownToggles.forEach(toggle => {
                        toggle.addEventListener('click', function() {
                            const dropdown = this.closest('.sidebar-dropdown');
                            dropdown.classList.toggle('show');
                        });
                    });

                    // User profile dropdown
                    const userProfile = document.querySelector('.user-profile');
                    const userDropdown = document.querySelector('.user-dropdown');

                    if (userProfile && userDropdown) {
                        userProfile.addEventListener('click', function(e) {
                            e.stopPropagation();
                            userDropdown.classList.toggle('show');
                        });

                        document.addEventListener('click', function(e) {
                            if (!userProfile.contains(e.target)) {
                                userDropdown.classList.remove('show');
                            }
                        });
                    }

                    // Mobile sidebar functionality
                    const mobileToggle = document.querySelector('.mobile-toggle');
                    const mobileOverlay = document.querySelector('.mobile-overlay');

                    if (mobileToggle && mobileOverlay) {
                        mobileToggle.addEventListener('click', function() {
                            sidebar.classList.toggle('mobile-show');
                            mobileOverlay.classList.toggle('show');
                        });

                        mobileOverlay.addEventListener('click', function() {
                            sidebar.classList.remove('mobile-show');
                            mobileOverlay.classList.remove('show');
                        });
                    }
                });
            </script>

            <?= $this->renderSection('scripts') ?>
</body>

</html>