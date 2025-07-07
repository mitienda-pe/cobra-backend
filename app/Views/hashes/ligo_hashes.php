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
        <div class="card-body p-0">
            <table class="table table-striped table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Hash ID (Order)</th>
                        <th>Hash Real de LIGO</th>
                        <th>Invoice ID</th>
                        <th>Instalment ID</th>
                        <th>Monto</th>
                        <th>Moneda</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($hashes)): ?>
                        <tr><td colspan="11" class="text-center">No hay hashes generados.</td></tr>
                    <?php else: foreach ($hashes as $h): ?>
                        <tr>
                            <td><?= esc($h['id']) ?></td>
                            <td class="hash-id">
                                <code><?= esc($h['order_id']) ?></code>
                            </td>
                            <td class="real-hash">
                                <?php if (!empty($h['real_hash'])): ?>
                                    <code class="text-success"><?= esc(substr($h['real_hash'], 0, 50)) ?><?= strlen($h['real_hash']) > 50 ? '...' : '' ?></code>
                                    <?php if (strlen($h['real_hash']) > 50): ?>
                                        <br><small class="text-muted">Ver completo en acciones</small>
                                    <?php endif; ?>
                                <?php elseif (!empty($h['hash_error'])): ?>
                                    <span class="text-danger">Error</span>
                                    <br><small class="text-muted"><?= esc(substr($h['hash_error'], 0, 30)) ?>...</small>
                                <?php else: ?>
                                    <span class="text-warning">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($h['invoice_id']) ?></td>
                            <td><?= esc($h['instalment_id'] ?? '-') ?></td>
                            <td><?= esc($h['amount']) ?></td>
                            <td><?= esc($h['currency']) ?></td>
                            <td><?= esc($h['description']) ?></td>
                            <td>
                                <?php if (!empty($h['real_hash'])): ?>
                                    <span class="badge bg-success">Completo</span>
                                <?php elseif (!empty($h['hash_error'])): ?>
                                    <span class="badge bg-danger">Error</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($h['created_at']) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if (empty($h['real_hash']) && empty($h['hash_error'])): ?>
                                        <button class="btn btn-warning btn-sm" onclick="requestHash('<?= esc($h['id']) ?>', '<?= esc($h['order_id']) ?>')">
                                            <i class="bi bi-arrow-clockwise"></i> Solicitar Hash
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-info btn-sm" onclick="viewDetails('<?= esc($h['id']) ?>')">
                                        <i class="bi bi-eye"></i> Ver
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
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
