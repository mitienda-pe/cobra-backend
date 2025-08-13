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
                    <h3 class="card-title">
                        <i class="bi bi-gear-wide-connected"></i> 
                        Configuración Centralizada de Ligo
                    </h3>
                    <div class="card-tools">
                        <span class="badge bg-info text-white">
                            <i class="bi bi-info-circle"></i> 
                            Configuración global para todas las organizaciones
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-lightbulb"></i>
                        <strong>Configuración Centralizada:</strong> 
                        Todas las organizaciones usarán estas credenciales para operaciones de Ligo. 
                        Los pagos van a la cuenta centralizada del superadmin.
                        <br><br>
                        <small>
                            <strong>Nota:</strong> Solo una configuración puede estar activa a la vez. 
                            Activa DEV para usar credenciales de sandbox/pruebas, o PROD para operaciones reales.
                        </small>
                    </div>

                    <?php if (empty($configs)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            No hay configuraciones de Ligo disponibles.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Entorno</th>
                                        <th>Estado</th>
                                        <th>Usuario</th>
                                        <th>Company ID</th>
                                        <th>Account ID</th>
                                        <th>Configuración</th>
                                        <th>Última actualización</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($configs as $config): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?= $config['environment'] === 'prod' ? 'danger' : 'warning' ?>">
                                                    <?= strtoupper($config['environment']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($config['is_active'] && $config['enabled']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle"></i> Activa
                                                    </span>
                                                <?php elseif ($config['enabled']): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="bi bi-pause-circle"></i> Habilitada
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="bi bi-x-circle"></i> Deshabilitada
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code><?= esc($config['username'] ?? 'No configurado') ?></code>
                                            </td>
                                            <td>
                                                <small><code><?= esc(substr($config['company_id'] ?? '', 0, 8)) ?>...</code></small>
                                            </td>
                                            <td>
                                                <small><code><?= esc($config['account_id'] ?? 'No configurado') ?></code></small>
                                            </td>
                                            <td>
                                                <?php
                                                $isComplete = !empty($config['username']) && 
                                                             !empty($config['password']) && 
                                                             !empty($config['company_id']) && 
                                                             !empty($config['private_key']);
                                                ?>
                                                <?php if ($isComplete): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check"></i> Completa
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-exclamation"></i> Incompleta
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= $config['updated_at'] ? date('d/m/Y H:i', strtotime($config['updated_at'])) : 'Nunca' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="<?= site_url('superadmin/ligo-config/edit/' . $config['id']) ?>" 
                                                       class="btn btn-outline-primary" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    
                                                    <?php if ($config['is_active']): ?>
                                                        <button type="button" 
                                                                class="btn btn-success" 
                                                                disabled
                                                                title="Configuración <?= strtoupper($config['environment']) ?> está activa - todas las operaciones usan estas credenciales">
                                                            <i class="bi bi-check-circle"></i> Activa
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" 
                                                                class="btn btn-outline-success btn-set-active" 
                                                                data-id="<?= $config['id'] ?>" 
                                                                title="Activar configuración <?= strtoupper($config['environment']) ?> - cambiará todas las operaciones a estas credenciales">
                                                            <i class="bi bi-play"></i> Activar
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($isComplete): ?>
                                                        <button type="button" 
                                                                class="btn btn-outline-info btn-test" 
                                                                data-id="<?= $config['id'] ?>" 
                                                                title="Probar">
                                                            <i class="bi bi-lightning"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-info-circle"></i> Información importante:</h6>
                            <ul class="mb-0">
                                <li><strong>Configuración DEV:</strong> Usa credenciales de sandbox/pruebas de Ligo</li>
                                <li><strong>Configuración PROD:</strong> Usa credenciales reales de producción de Ligo</li>
                                <li><strong>Solo UNA configuración puede estar activa a la vez</strong></li>
                                <li><strong>Cambia manualmente entre DEV y PROD según necesites</strong></li>
                                <li><strong>Todas las organizaciones usarán la configuración activa</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Cargando...</span>
                </div>
                <p class="mt-2 mb-0">Procesando...</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set Active Configuration
    document.querySelectorAll('.btn-set-active:not(.disabled)').forEach(function(button) {
        button.addEventListener('click', function() {
            const configId = this.getAttribute('data-id');
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
            
            loadingModal.show();
            
            fetch(`<?= site_url('superadmin/ligo-config/set-active') ?>/${configId}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="X-CSRF-TOKEN"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                loadingModal.hide();
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                loadingModal.hide();
                alert('Error de conexión: ' + error.message);
            });
        });
    });

    // Test Configuration
    document.querySelectorAll('.btn-test').forEach(function(button) {
        button.addEventListener('click', function() {
            const configId = this.getAttribute('data-id');
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
            
            loadingModal.show();
            
            fetch(`<?= site_url('superadmin/ligo-config/test') ?>/${configId}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="X-CSRF-TOKEN"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                loadingModal.hide();
                if (data.success) {
                    alert('✅ Prueba exitosa: ' + data.message);
                } else {
                    alert('❌ Error en prueba: ' + data.message);
                }
            })
            .catch(error => {
                loadingModal.hide();
                alert('Error de conexión: ' + error.message);
            });
        });
    });
});
</script>
<?= $this->endSection() ?>