<?= $this->extend('layout/default') ?>

<?= $this->section('content') ?>
<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Pago con QR - Ligo</h4>
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title">Factura #<?= $invoice['invoice_number'] ?></h5>
                    <p class="card-text">Monto a pagar: <?= $invoice['currency'] ?> <?= number_format($invoice['amount'], 2) ?></p>
                    
                    <?php if (isset($qr_image_url) && !empty($qr_image_url)): ?>
                        <div class="my-4">
                            <img src="<?= $qr_image_url ?>" alt="QR Code" class="img-fluid" style="max-width: 250px;">
                        </div>
                        <p class="text-muted">Escanea el código QR con tu aplicación de Ligo para realizar el pago</p>
                        
                        <?php if (isset($expiration)): ?>
                            <p class="text-danger">Este código QR expirará en <span id="countdown"></span></p>
                            <script>
                                // Set the expiration time
                                const expirationTime = new Date("<?= $expiration ?>").getTime();
                                
                                // Update the countdown every second
                                const countdownTimer = setInterval(function() {
                                    const now = new Date().getTime();
                                    const distance = expirationTime - now;
                                    
                                    // Calculate time components
                                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                                    
                                    // Display the countdown
                                    document.getElementById("countdown").innerHTML = 
                                        minutes + "m " + seconds + "s";
                                    
                                    // If expired, redirect to invoice page
                                    if (distance < 0) {
                                        clearInterval(countdownTimer);
                                        document.getElementById("countdown").innerHTML = "EXPIRADO";
                                        setTimeout(() => {
                                            window.location.href = "<?= site_url('invoices/view/' . $invoice['id']) ?>";
                                        }, 3000);
                                    }
                                }, 1000);
                            </script>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            No se pudo generar el código QR. Por favor, intente nuevamente.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-6">
                            <a href="<?= site_url('invoices/view/' . $invoice['id']) ?>" class="btn btn-secondary btn-block">
                                <i class="fas fa-arrow-left"></i> Volver
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="<?= site_url('payment/ligo/qr/' . $invoice['id']) ?>" class="btn btn-primary btn-block">
                                <i class="fas fa-sync"></i> Regenerar QR
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
