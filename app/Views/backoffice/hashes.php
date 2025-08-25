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
                    <h3 class="card-title">Hashes QR</h3>
                    <div class="card-tools">
                        <a href="<?= base_url('backoffice') ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                        <a href="<?= base_url('webhooks/ligo-logs') ?>" class="btn btn-info">
                            <i class="fas fa-bell"></i> Ver Notificaciones
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Gestión de hashes QR generados para todas las organizaciones.
                    </div>

                    <?php
                    // Show active Ligo configuration
                    $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
                    $activeConfig = $superadminLigoConfigModel->where('enabled', 1)->where('is_active', 1)->first();
                    ?>
                    <?php if ($activeConfig): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-gear-wide-connected"></i>
                            <strong>Configuración activa:</strong>
                            <span class="badge bg-<?= $activeConfig['environment'] === 'prod' ? 'danger' : 'warning' ?> ms-1">
                                <?= strtoupper($activeConfig['environment']) ?>
                            </span>
                            <small class="d-block mt-1">
                                Usuario: <code><?= esc($activeConfig['username']) ?></code> |
                                Company: <code><?= esc(substr($activeConfig['company_id'], 0, 8)) ?>...</code>
                            </small>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Hash ID (Order)</th>
                                    <th>ID QR</th>
                                    <th>Invoice</th>
                                    <th>Cuota</th>
                                    <th>Monto</th>
                                    <th>Estado QR</th>
                                    <th>Estado Pago</th>
                                    <th>Entorno</th>
                                    <th>Creado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Función para normalizar montos consistentemente (fuera del bucle)
                                if (!function_exists('normalizeAmount')) {
                                    function normalizeAmount($amount)
                                    {
                                        // Convertir centavos a soles si el monto parece estar en centavos
                                        if ($amount >= 100) {
                                            return $amount / 100;
                                        }
                                        return $amount;
                                    }
                                }

                                if (empty($hashes)): ?>
                                    <tr>
                                        <td colspan="11" class="text-center">No hay hashes generados.</td>
                                    </tr>
                                    <?php else: foreach ($hashes as $h):
                                        // Calcular el monto correcto
                                        $displayAmount = normalizeAmount($h['amount'] ?? 0);

                                        // Determinar el entorno y badge
                                        $environment = $h['environment'] ?? 'dev';
                                        $envBadgeClass = $environment === 'prod' ? 'bg-danger' : 'bg-warning text-dark';
                                        $envText = $environment === 'prod' ? 'PROD' : 'DEV';

                                        // Usar el estado real calculado en el controlador
                                        $paymentStatus = 'No pagado';
                                        $paymentBadgeClass = 'bg-warning';
                                        if (isset($h['instalment_id']) && $h['instalment_id']) {
                                            if (isset($h['is_actually_paid']) && $h['is_actually_paid']) {
                                                $paymentStatus = 'Pagado';
                                                $paymentBadgeClass = 'bg-success';
                                            } else {
                                                $paymentStatus = 'Pendiente';
                                                $paymentBadgeClass = 'bg-warning';
                                            }

                                            // Mostrar el monto total pagado si existe
                                            if (isset($h['total_paid']) && $h['total_paid'] > 0) {
                                                $paymentStatus .= '<br><small>S/ ' . number_format($h['total_paid'], 2) . '</small>';
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td><?= esc($h['id']) ?></td>
                                            <td class="hash-id">
                                                <code class="small"><?= esc($h['order_id']) ?></code>
                                            </td>
                                            <td>
                                                <?php if (!empty($h['id_qr'])): ?>
                                                    <code class="small text-info"><?= esc($h['id_qr']) ?></code>
                                                    <button class="btn btn-sm btn-outline-secondary ms-1" onclick="copyToClipboard('<?= esc($h['id_qr']) ?>')" title="Copiar ID QR">
                                                        <i class="fas fa-clipboard"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($h['invoice_id']): ?>
                                                    <div>
                                                        <strong>#<?= esc($h['invoice_id']) ?></strong>
                                                        <?php if (isset($h['invoice_number'])): ?>
                                                            <br><small class="text-muted"><?= esc($h['invoice_number']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($h['instalment_id']): ?>
                                                    <div>
                                                        <strong>Cuota <?= esc($h['instalment_number'] ?? '#' . $h['instalment_id']) ?></strong>
                                                        <br><small class="text-muted">ID: <?= esc($h['instalment_id']) ?></small>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong>S/ <?= number_format($displayAmount, 2) ?></strong>
                                                <?php if ($h['currency'] && $h['currency'] !== 'PEN'): ?>
                                                    <br><small class="text-muted"><?= esc($h['currency']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($h['real_hash'])): ?>
                                                    <span class="badge bg-success">QR Generado</span>
                                                <?php elseif (!empty($h['hash_error'])): ?>
                                                    <span class="badge bg-danger">Error</span>
                                                    <br><small class="text-muted"><?= esc(substr($h['hash_error'], 0, 30)) ?>...</small>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pendiente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $paymentBadgeClass ?>"><?= $paymentStatus ?></span>
                                            </td>
                                            <td>
                                                <span class="badge <?= $envBadgeClass ?>"><?= $envText ?></span>
                                            </td>
                                            <td>
                                                <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php
                                                    // Don't show request button if QR is older than 24 hours (likely expired)
                                                    $qrAge = time() - strtotime($h['created_at']);
                                                    $isOldQR = $qrAge > (24 * 60 * 60); // 24 hours
                                                    ?>
                                                    <?php if (empty($h['real_hash']) && empty($h['hash_error']) && !$isOldQR): ?>
                                                        <button class="btn btn-warning btn-sm" onclick="requestHash('<?= esc($h['id']) ?>', '<?= esc($h['order_id']) ?>')" title="Solicitar Hash">
                                                            <i class="fas fa-sync"></i>
                                                        </button>
                                                    <?php elseif (empty($h['real_hash']) && empty($h['hash_error']) && $isOldQR): ?>
                                                        <button class="btn btn-secondary btn-sm" disabled title="QR muy antiguo (>24h), probablemente expirado">
                                                            <i class="fas fa-clock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-info btn-sm" onclick="viewDetails('<?= esc($h['id']) ?>')" title="Ver detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($h['invoice_id']): ?>
                                                        <a href="<?= site_url('invoices/view/' . ($h['invoice_uuid'] ?? $h['invoice_id'])) ?>" class="btn btn-outline-primary btn-sm" title="Ver factura" target="_blank">
                                                            <i class="fas fa-receipt"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver detalles del hash -->
<div class="modal fade" id="hashDetailsModal" tabindex="-1" aria-labelledby="hashDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="hashDetailsModalLabel">
                    <i class="fas fa-qrcode"></i> Detalles del Hash QR
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="hashDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando detalles...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para solicitar hash -->
<div class="modal fade" id="requestHashModal" tabindex="-1" aria-labelledby="requestHashModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestHashModalLabel">
                    <i class="fas fa-sync"></i> Solicitar Hash Real de LIGO
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Deseas solicitar el hash real de LIGO para el QR ID: <strong id="qrIdToRequest"></strong>?</p>
                <div id="requestResult" class="alert" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmRequestHash()">Solicitar Hash</button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentHashId = null;
    let currentOrderId = null;

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('ID QR copiado al portapapeles', 'success');
        }, function(err) {
            console.error('Error al copiar: ', err);
            showToast('Error al copiar al portapapeles', 'danger');
        });
    }

    function copyHashToClipboard(hash) {
        navigator.clipboard.writeText(hash).then(function() {
            showToast('Hash copiado al portapapeles', 'success');
        }, function(err) {
            console.error('Error al copiar hash: ', err);
            showToast('Error al copiar hash', 'danger');
        });
    }

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0 position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        document.body.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();

        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    function requestHash(hashId, orderId) {
        currentHashId = hashId;
        currentOrderId = orderId;
        document.getElementById('qrIdToRequest').textContent = orderId;
        document.getElementById('requestResult').style.display = 'none';

        const modal = new bootstrap.Modal(document.getElementById('requestHashModal'));
        modal.show();
    }

    function confirmRequestHash() {
        const resultDiv = document.getElementById('requestResult');
        resultDiv.style.display = 'none';

        // Cambiar el botón para mostrar que está procesando
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = 'Solicitando...';
        btn.disabled = true;

        // Obtener token CSRF
        const csrfToken = document.querySelector('meta[name="X-CSRF-TOKEN"]').getAttribute('content');

        fetch('<?= base_url('api/ligo-hashes/request-real-hash') ?>/' + currentHashId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                btn.textContent = originalText;
                btn.disabled = false;

                if (data.success) {
                    resultDiv.className = 'alert alert-success';
                    resultDiv.textContent = 'Hash solicitado exitosamente. La página se recargará automáticamente.';
                    resultDiv.style.display = 'block';

                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    resultDiv.className = 'alert alert-danger';
                    resultDiv.textContent = 'Error: ' + (data.error || data.message || 'Error desconocido');
                    resultDiv.style.display = 'block';
                }
            })
            .catch(error => {
                btn.textContent = originalText;
                btn.disabled = false;

                resultDiv.className = 'alert alert-danger';
                resultDiv.textContent = 'Error de conexión: ' + error.message;
                resultDiv.style.display = 'block';
            });
    }

    function viewDetails(hashId) {
        const modal = new bootstrap.Modal(document.getElementById('hashDetailsModal'));
        const contentDiv = document.getElementById('hashDetailsContent');

        contentDiv.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-2">Cargando detalles...</p>
        </div>
    `;

        modal.show();

        // Obtener token CSRF
        const csrfToken = document.querySelector('meta[name="X-CSRF-TOKEN"]').getAttribute('content');

        fetch('<?= base_url('api/ligo-hashes/details') ?>/' + hashId, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const hash = data.hash;
                    contentDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Información General</h6>
                        <table class="table table-sm">
                            <tr><td><strong>ID:</strong></td><td>${hash.id}</td></tr>
                            <tr><td><strong>Hash ID (Order):</strong></td><td><code>${hash.order_id}</code></td></tr>
                            <tr><td><strong>Invoice ID:</strong></td><td>${hash.invoice_id || '-'}</td></tr>
                            <tr><td><strong>Instalment ID:</strong></td><td>${hash.instalment_id || '-'}</td></tr>
                            <tr><td><strong>Monto:</strong></td><td>S/ ${hash.amount} ${hash.currency || ''}</td></tr>
                            <tr><td><strong>Entorno:</strong></td><td><span class="badge bg-${hash.environment === 'prod' ? 'danger' : 'warning'}">${hash.environment?.toUpperCase() || 'DEV'}</span></td></tr>
                            <tr><td><strong>Creado:</strong></td><td>${hash.created_at}</td></tr>
                        </table>
                        
                        ${hash.real_hash ? `
                        <hr>
                        <div class="text-center">
                            <h6>Código QR:</h6>
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(hash.real_hash)}" 
                                 alt="QR Code" 
                                 class="img-fluid border rounded mt-2"
                                 style="max-width: 200px;">
                            <br>
                            <small class="text-muted">Generado desde hash almacenado</small>
                        </div>
                        ` : ''}
                    </div>
                    <div class="col-md-6">
                        <h6>Descripción</h6>
                        <p class="text-muted">${hash.description || 'Sin descripción'}</p>
                        
                        <h6>Hash Real de LIGO</h6>
                        ${hash.real_hash ? 
                            `<textarea class="form-control" rows="4" readonly>${hash.real_hash}</textarea>
                             <button class="btn btn-sm btn-outline-secondary mt-2" onclick="copyHashToClipboard('${hash.real_hash.replace(/'/g, "\\'")}')">
                                <i class="fas fa-clipboard"></i> Copiar Hash
                             </button>` : 
                            '<div class="alert alert-warning">Hash no disponible</div>'
                        }
                        
                        ${hash.hash_error ? 
                            `<hr><h6>Error:</h6><div class="alert alert-danger"><small>${hash.hash_error}</small></div>` : 
                            ''
                        }
                    </div>
                </div>
            `;
                } else {
                    contentDiv.innerHTML = `<div class="alert alert-danger">Error: ${data.error || 'Error desconocido'}</div>`;
                }
            })
            .catch(error => {
                contentDiv.innerHTML = `<div class="alert alert-danger">Error de conexión: ${error.message}</div>`;
            });
    }
</script>
<?= $this->endSection() ?>