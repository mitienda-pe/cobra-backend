<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Hashes QR Ligo<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Hashes QR generados por Ligo</h2>
        <a href="<?= site_url('webhooks/ligo-logs') ?>" class="btn btn-info">
            <i class="bi bi-bell"></i> Ver Notificaciones de Ligo
        </a>
    </div>
    <div class="card">
        <div class="card-body p-0 overflow-scroll">
            <table class="table table-striped table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Hash ID (Order)</th>
                        <th>ID QR</th>
                        <th>Invoice</th>
                        <th>Cuota</th>
                        <th>Monto</th>
                        <th>Estado QR</th>
                        <th>Estado Pago</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($hashes)): ?>
                        <tr>
                            <td colspan="10" class="text-center">No hay hashes generados.</td>
                        </tr>
                        <?php else: foreach ($hashes as $h): 
                            // Calcular el monto correcto
                            $displayAmount = $h['amount'] ?? 0;
                            if ($displayAmount >= 100) {
                                $displayAmount = $displayAmount / 100; // Convertir centavos a soles
                            }
                            
                            // Buscar información de pago para esta cuota
                            $paymentStatus = 'No pagado';
                            $paymentBadgeClass = 'bg-warning';
                            if (isset($h['instalment_id']) && $h['instalment_id']) {
                                // Verificar si hay pagos para esta cuota (esto requiere que el controlador pase la información)
                                $instalmentStatus = $h['instalment_status'] ?? 'unknown';
                                if ($instalmentStatus === 'paid') {
                                    $paymentStatus = 'Pagado';
                                    $paymentBadgeClass = 'bg-success';
                                } elseif ($instalmentStatus === 'pending') {
                                    $paymentStatus = 'Pendiente';
                                    $paymentBadgeClass = 'bg-warning';
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
                                            <i class="bi bi-clipboard"></i>
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
                                    <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if (empty($h['real_hash']) && empty($h['hash_error'])): ?>
                                            <button class="btn btn-warning btn-sm" onclick="requestHash('<?= esc($h['id']) ?>', '<?= esc($h['order_id']) ?>')" title="Solicitar Hash">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-info btn-sm" onclick="viewDetails('<?= esc($h['id']) ?>')" title="Ver detalles">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($h['invoice_id']): ?>
                                            <a href="<?= site_url('invoices/view/' . ($h['invoice_uuid'] ?? $h['invoice_id'])) ?>" class="btn btn-outline-primary btn-sm" title="Ver factura" target="_blank">
                                                <i class="bi bi-receipt"></i>
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

<!-- Modal para ver detalles del hash -->
<div class="modal fade" id="hashDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Hash QR</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="hashDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para solicitar hash -->
<div class="modal fade" id="requestHashModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Solicitar Hash Real de LIGO</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
            // Mostrar notificación de éxito
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed';
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ID QR copiado al portapapeles
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
        }, function(err) {
            console.error('Error al copiar: ', err);
        });
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

        fetch('<?= site_url('api/ligo-hashes/request-real-hash') ?>/' + currentHashId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
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
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    `;

        modal.show();

        fetch('<?= site_url('api/ligo-hashes/details') ?>/' + hashId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const hash = data.hash;
                    contentDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>ID:</strong> ${hash.id}<br>
                        <strong>Hash ID (Order):</strong> <code>${hash.order_id}</code><br>
                        <strong>Invoice ID:</strong> ${hash.invoice_id || '-'}<br>
                        <strong>Instalment ID:</strong> ${hash.instalment_id || '-'}<br>
                        <strong>Monto:</strong> ${hash.amount} ${hash.currency}<br>
                        <strong>Creado:</strong> ${hash.created_at}
                    </div>
                    <div class="col-md-6">
                        <strong>Descripción:</strong><br>
                        <p class="text-muted">${hash.description || '-'}</p>
                        
                        <strong>Hash Real de LIGO:</strong><br>
                        ${hash.real_hash ? 
                            `<textarea class="form-control" rows="4" readonly>${hash.real_hash}</textarea>` : 
                            '<span class="text-warning">No disponible</span>'
                        }
                        
                        ${hash.hash_error ? 
                            `<br><strong>Error:</strong><br><div class="alert alert-danger">${hash.hash_error}</div>` : 
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