<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Historial de Transferencias Ligo
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Historial de Transferencias Ligo</h3>
                    <div class="card-tools">
                        <a href="<?= base_url('backoffice/transfer') ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nueva Transferencia
                        </a>
                        <a href="<?= base_url('backoffice') ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($transfers)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            No hay transferencias registradas aún. <a href="<?= base_url('backoffice/transfer') ?>">Crea tu primera transferencia</a>.
                        </div>
                    <?php else: ?>
                        <!-- Statistics Cards -->
                        <?php if (isset($stats)): ?>
                            <div class="row mb-4">
                                <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="card border-left-primary shadow h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Transferencias</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total']) ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-exchange-alt fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="card border-left-success shadow h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Exitosas</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['successful']) ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="card border-left-info shadow h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Procesando</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['processing'] ?? 0) ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-spinner fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="card border-left-warning shadow h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pendientes</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['pending']) ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6 mb-4">
                                    <div class="card border-left-danger shadow h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Fallidas</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['failed'] ?? 0) ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-xl-12 mb-4">
                                    <div class="card border-left-primary shadow h-100 py-2">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Monto Total Transferido</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800">S/ <?= number_format($stats['total_amount'], 2) ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Transfers Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha</th>
                                        <th>Ref. Transacción</th>
                                        <th>CCI Destino</th>
                                        <th>Beneficiario</th>
                                        <th>Monto</th>
                                        <th>Comisión</th>
                                        <th>Estado</th>
                                        <th>Usuario</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transfers as $transfer): ?>
                                        <tr>
                                            <td><?= $transfer['id'] ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($transfer['created_at'])) ?></td>
                                            <td>
                                                <code class="small"><?= esc(substr($transfer['reference_transaction_id'], 0, 20)) ?></code>
                                                <?php if (strlen($transfer['reference_transaction_id']) > 20): ?>
                                                    <span class="text-muted">...</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><code><?= esc($transfer['creditor_cci']) ?></code></td>
                                            <td><?= esc($transfer['creditor_name']) ?></td>
                                            <td class="text-right">
                                                <strong><?= $transfer['currency'] ?> <?= number_format($transfer['amount'], 2) ?></strong>
                                            </td>
                                            <td class="text-right">
                                                <?= $transfer['currency'] ?> <?= number_format($transfer['fee_amount'], 2) ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $badgeClass = 'secondary';
                                                $statusText = ucfirst($transfer['status']);
                                                switch ($transfer['status']) {
                                                    case 'completed':
                                                        $badgeClass = 'success';
                                                        $statusText = 'Completada';
                                                        break;
                                                    case 'processing':
                                                        $badgeClass = 'info';
                                                        $statusText = 'Procesando';
                                                        break;
                                                    case 'pending':
                                                        $badgeClass = 'warning';
                                                        $statusText = 'Pendiente';
                                                        break;
                                                    case 'failed':
                                                        $badgeClass = 'danger';
                                                        $statusText = 'Fallida';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge badge-<?= $badgeClass ?>"><?= $statusText ?></span>
                                                <?php if (!empty($transfer['response_code'])): ?>
                                                    <small class="d-block text-muted">Código: <?= esc($transfer['response_code']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= isset($transfer['user_name']) ? esc($transfer['user_name']) : 'Sistema' ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="showTransferDetails(<?= $transfer['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if (!empty($transfer['ligo_response'])): ?>
                                                    <button class="btn btn-sm btn-secondary" onclick="showLigoResponse(<?= $transfer['id'] ?>)">
                                                        <i class="fas fa-code"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transfer Details Modal -->
<div class="modal fade" id="transferDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles de Transferencia</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="transferDetailsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ligo Response Modal -->
<div class="modal fade" id="ligoResponseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Respuesta de Ligo API</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="ligoResponseContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'block';
    modal.classList.add('show');
    document.body.classList.add('modal-open');
    
    // Create backdrop
    const backdrop = document.createElement('div');
    backdrop.classList.add('modal-backdrop', 'fade', 'show');
    backdrop.id = modalId + 'Backdrop';
    document.body.appendChild(backdrop);
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'none';
    modal.classList.remove('show');
    document.body.classList.remove('modal-open');
    
    // Remove backdrop
    const backdrop = document.getElementById(modalId + 'Backdrop');
    if (backdrop) {
        backdrop.remove();
    }
}

function showTransferDetails(transferId) {
    showModal('transferDetailsModal');
    
    // Reset content to loading
    document.getElementById('transferDetailsContent').innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="sr-only">Cargando...</span>
            </div>
        </div>
    `;
    
    fetch('<?= base_url('backoffice/transfer/details') ?>/' + transferId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const transfer = data.data;
                const content = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Información General</h6>
                            <table class="table table-sm">
                                <tr><th>ID:</th><td>${transfer.id}</td></tr>
                                <tr><th>Ref. Transacción:</th><td><code>${transfer.reference_transaction_id}</code></td></tr>
                                <tr><th>Account Inquiry ID:</th><td>${transfer.account_inquiry_id || 'N/A'}</td></tr>
                                <tr><th>Instruction ID:</th><td>${transfer.instruction_id || 'N/A'}</td></tr>
                                <tr><th>Fecha:</th><td>${new Date(transfer.created_at).toLocaleString()}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Detalles de Transferencia</h6>
                            <table class="table table-sm">
                                <tr><th>CCI Deudor:</th><td><code>${transfer.debtor_cci}</code></td></tr>
                                <tr><th>CCI Acreedor:</th><td><code>${transfer.creditor_cci}</code></td></tr>
                                <tr><th>Beneficiario:</th><td>${transfer.creditor_name}</td></tr>
                                <tr><th>Monto:</th><td><strong>${transfer.currency} ${parseFloat(transfer.amount).toFixed(2)}</strong></td></tr>
                                <tr><th>Comisión:</th><td>${transfer.currency} ${parseFloat(transfer.fee_amount).toFixed(2)}</td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Información Técnica</h6>
                            <table class="table table-sm">
                                <tr><th>Código de Comisión:</th><td>${transfer.fee_code}</td></tr>
                                <tr><th>Fee ID:</th><td>${transfer.fee_id || 'N/A'}</td></tr>
                                <tr><th>Canal:</th><td>${transfer.channel}</td></tr>
                                <tr><th>Tipo de Mensaje:</th><td>${transfer.message_type_id}</td></tr>
                                <tr><th>Tipo de Transacción:</th><td>${transfer.transaction_type}</td></tr>
                                <tr><th>Concepto:</th><td>${transfer.unstructured_information || 'N/A'}</td></tr>
                            </table>
                        </div>
                    </div>
                `;
                document.getElementById('transferDetailsContent').innerHTML = content;
            } else {
                document.getElementById('transferDetailsContent').innerHTML = '<div class="alert alert-danger">Error al cargar detalles</div>';
            }
        })
        .catch(error => {
            document.getElementById('transferDetailsContent').innerHTML = '<div class="alert alert-danger">Error de conexión</div>';
        });
}

function showLigoResponse(transferId) {
    showModal('ligoResponseModal');
    
    // Reset content to loading
    document.getElementById('ligoResponseContent').innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="sr-only">Cargando...</span>
            </div>
        </div>
    `;
    
    fetch('<?= base_url('backoffice/transfer/ligo-response') ?>/' + transferId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.ligo_response) {
                const response = JSON.parse(data.data.ligo_response);
                const content = '<pre class="bg-light p-3"><code>' + JSON.stringify(response, null, 2) + '</code></pre>';
                document.getElementById('ligoResponseContent').innerHTML = content;
            } else {
                document.getElementById('ligoResponseContent').innerHTML = '<div class="alert alert-info">No hay respuesta de Ligo disponible</div>';
            }
        })
        .catch(error => {
            document.getElementById('ligoResponseContent').innerHTML = '<div class="alert alert-danger">Error de conexión</div>';
        });
}

// Event listeners for closing modals
document.addEventListener('DOMContentLoaded', function() {
    // Close modal when clicking the X button
    document.querySelectorAll('[data-dismiss="modal"]').forEach(function(closeBtn) {
        closeBtn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            hideModal(modal.id);
        });
    });
    
    // Close modal when clicking outside the modal content
    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal(this.id);
            }
        });
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal.show');
            openModals.forEach(function(modal) {
                hideModal(modal.id);
            });
        }
    });
});
</script>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-danger { border-left: 0.25rem solid #e74a3b !important; }

/* Modal styles for vanilla JS */
.modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    outline: 0;
}

.modal.show {
    display: block !important;
}

.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1040;
    width: 100vw;
    height: 100vh;
    background-color: #000;
    opacity: 0.5;
}

.modal-backdrop.show {
    opacity: 0.5;
}

.modal-open {
    overflow: hidden;
}

.modal-dialog {
    position: relative;
    width: auto;
    margin: 0.5rem;
    pointer-events: none;
}

@media (min-width: 576px) {
    .modal-dialog {
        max-width: 500px;
        margin: 1.75rem auto;
    }
}

@media (min-width: 992px) {
    .modal-lg {
        max-width: 800px;
    }
    .modal-xl {
        max-width: 1140px;
    }
}

.modal-content {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    pointer-events: auto;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid rgba(0,0,0,.2);
    border-radius: 0.3rem;
    outline: 0;
}

.modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 1rem 1rem;
    border-bottom: 1px solid #dee2e6;
    border-top-left-radius: calc(0.3rem - 1px);
    border-top-right-radius: calc(0.3rem - 1px);
}

.modal-body {
    position: relative;
    flex: 1 1 auto;
    padding: 1rem;
}

.close {
    padding: 1rem 1rem;
    margin: -1rem -1rem -1rem auto;
    background-color: transparent;
    border: 0;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
    color: #000;
    text-shadow: 0 1px 0 #fff;
    opacity: .5;
    cursor: pointer;
}

.close:hover {
    opacity: .75;
}
</style>
<?= $this->endSection() ?>