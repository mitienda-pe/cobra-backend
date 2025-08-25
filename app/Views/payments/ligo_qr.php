<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= $title ?? 'Pago con QR' ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Pago con QR</h4>
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title">Factura #<?= $invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A' ?></h5>
                    <p class="card-text">Monto a pagar: <?= $invoice['currency'] ?? 'PEN' ?> <?= number_format($invoice['total_amount'] ?? $invoice['amount'] ?? 0, 2) ?></p>

                    <!-- 🚀 LOADING SPINNER -->
                    <div id="qr-loading" class="my-4" style="display: <?= isset($qr_image_url) && !empty($qr_image_url) ? 'none' : 'block' ?>;">
                        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Generando QR...</span>
                        </div>
                        <p class="text-muted">
                            <strong>Generando código QR...</strong><br>
                            <small id="loading-message">Conectando con proveedor...</small>
                        </p>
                        <div class="progress mt-3" style="height: 6px;">
                            <div id="loading-progress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 30%"></div>
                        </div>
                    </div>

                    <!-- ✅ QR GENERADO -->
                    <div id="qr-content" style="display: <?= isset($qr_image_url) && !empty($qr_image_url) ? 'block' : 'none' ?>;">
                        <?php if (isset($error_message) && !empty($error_message)): ?>
                            <div class="alert alert-danger">
                                <h5><i class="bi bi-exclamation-triangle-fill"></i> Error</h5>
                                <p><?= $error_message ?></p>
                                <p class="mb-0 small">Si el problema persiste, por favor contacte al administrador del sistema.</p>
                            </div>
                        <?php elseif (isset($qr_image_url) && !empty($qr_image_url)): ?>
                            <div class="my-4">
                                <img id="qr-image" src="<?= $qr_image_url ?>" alt="QR Code" class="img-fluid" style="max-width: 250px;">
                            </div>
                            <p class="text-muted" id="qr-instructions">Escanea el código QR con tu aplicación de Yape o Plin para realizar el pago</p>

                            <!-- 🚀 CACHE INFO -->
                            <?php if (isset($is_cached) && $is_cached): ?>
                                <div class="alert alert-info">
                                    <small><i class="bi bi-lightning-charge-fill"></i> <strong>Carga rápida:</strong> QR servido desde cache (<?= isset($cache_age) ? round($cache_age, 1) . ' min' : 'reciente' ?>)</small>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($is_demo) && $is_demo): ?>
                                <div class="alert alert-warning">
                                    <p><strong>Modo Demostración</strong></p>
                                    <p class="mb-0">Este es un QR de demostración. Para habilitar pagos reales con QR, configure las credenciales en la sección de configuración de la organización.</p>
                                </div>
                            <?php endif; ?>

                            <p class="text-muted" id="qr-expiration">
                                <?php if (isset($expiration) && !empty($expiration)): ?>
                                    <small>Este código QR expira el: <?= date('d/m/Y H:i', strtotime($expiration)) ?></small>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- ❌ ERROR CONTAINER -->
                    <div id="qr-error" style="display: none;">
                        <div class="alert alert-danger">
                            <h5><i class="bi bi-exclamation-triangle-fill"></i> Error</h5>
                            <p id="error-message">No se pudo generar el código QR. Por favor, intente nuevamente.</p>
                            <p class="mb-0 small">Si el problema persiste, por favor contacte al administrador del sistema.</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-6">
                            <a href="<?= site_url('invoices/view/' . $invoice['id']) ?>" class="btn btn-secondary btn-block">
                                <i class="bi bi-arrow-left"></i> Volver
                            </a>
                        </div>
                        <div class="col-6">
                            <button id="regenerate-qr" class="btn btn-primary btn-block" onclick="generateQR()">
                                <i class="bi bi-arrow-repeat"></i> Regenerar QR
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let progressInterval;
    const invoiceId = '<?= $invoice['uuid'] ?? $invoice['id'] ?>';
    const instalmentId = <?= isset($instalment_id) ? $instalment_id : 'null' ?>;

    // 🚀 Generar QR de forma asíncrona si no existe
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!isset($qr_image_url) || empty($qr_image_url)): ?>
            generateQR();
        <?php endif; ?>
    });

    function generateQR() {
        // Mostrar loading y ocultar contenido
        document.getElementById('qr-loading').style.display = 'block';
        document.getElementById('qr-content').style.display = 'none';
        document.getElementById('qr-error').style.display = 'none';

        // Deshabilitar botón de regenerar
        const regenerateBtn = document.getElementById('regenerate-qr');
        regenerateBtn.disabled = true;
        regenerateBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Generando...';

        // Simular progreso
        startProgress();

        // Construir URL para AJAX
        let ajaxUrl = '<?= site_url('payment/ligo/ajax-qr') ?>/' + invoiceId;
        if (instalmentId) {
            ajaxUrl += '/' + instalmentId;
        }

        fetch(ajaxUrl)
            .then(response => response.json())
            .then(data => {
                clearInterval(progressInterval);

                if (data.success && data.qr_image_url) {
                    // ✅ Mostrar QR exitoso
                    document.getElementById('qr-image').src = data.qr_image_url;
                    document.getElementById('qr-instructions').textContent = 'Escanea el código QR con tu aplicación de Yape o Plin para realizar el pago';

                    // Mostrar info de expiración
                    if (data.expiration) {
                        const expirationDate = new Date(data.expiration);
                        document.getElementById('qr-expiration').innerHTML =
                            '<small>Este código QR expira el: ' + expirationDate.toLocaleString('es-ES') + '</small>';
                    }

                    // Mostrar info de cache
                    if (data.is_cached) {
                        document.getElementById('qr-content').insertAdjacentHTML('afterbegin',
                            `<div class="alert alert-info">
                            <small><i class="bi bi-lightning-charge-fill"></i> <strong>Carga rápida:</strong> QR servido desde cache (${data.cache_age} min)</small>
                        </div>`
                        );
                    }

                    document.getElementById('qr-loading').style.display = 'none';
                    document.getElementById('qr-content').style.display = 'block';

                    console.log('✅ QR generado exitosamente:', data.is_cached ? 'desde cache' : 'nuevo');
                } else {
                    // ❌ Mostrar error
                    showError(data.error_message || 'No se pudo generar el código QR');
                }
            })
            .catch(error => {
                clearInterval(progressInterval);
                console.error('Error:', error);
                showError('Error de conexión. Por favor, intente nuevamente.');
            })
            .finally(() => {
                // Rehabilitar botón
                regenerateBtn.disabled = false;
                regenerateBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Regenerar QR';
            });
    }

    function startProgress() {
        let progress = 30;
        const messages = [
            'Conectando con proveedor...',
            'Autenticando...',
            'Generando QR...',
            'Finalizando...'
        ];
        let messageIndex = 0;

        progressInterval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;

            document.getElementById('loading-progress').style.width = progress + '%';

            // Cambiar mensaje cada 2 segundos
            if (Math.random() > 0.7 && messageIndex < messages.length - 1) {
                messageIndex++;
                document.getElementById('loading-message').textContent = messages[messageIndex];
            }
        }, 500);
    }

    function showError(message) {
        document.getElementById('error-message').textContent = message;
        document.getElementById('qr-loading').style.display = 'none';
        document.getElementById('qr-content').style.display = 'none';
        document.getElementById('qr-error').style.display = 'block';
    }
</script>

<?= $this->endSection() ?>