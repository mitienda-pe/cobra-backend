<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Registrar Pago<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Registrar Pago</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= site_url('payments') ?>">Pagos</a></li>
                <li class="breadcrumb-item active">Registrar Pago</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form action="<?= site_url('payments/create') ?>" method="post">
                    <?= csrf_field() ?>
                    
                    
                    <?php if (!empty($invoice)): ?>
                        <!-- Si se pasa una factura específica, mostrar su información -->
                        <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                        
                        <div class="alert alert-info">
                            <h5>Información de la Cuenta por Cobrar</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Factura:</strong> <?= esc($invoice['invoice_number']) ?></p>
                                    <p><strong>Cliente:</strong> <?= esc($client['business_name']) ?></p>
                                    <p><strong>Documento:</strong> <?= esc($client['document_number']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Concepto:</strong> <?= esc($invoice['concept']) ?></p>
                                    <p><strong>Monto Total:</strong> $<?= number_format($invoice['amount'], 2) ?></p>
                                    <p><strong>Vencimiento:</strong> <?= date('d/m/Y', strtotime($invoice['due_date'])) ?></p>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-12">
                                    <p><strong>Total Pagado:</strong> $<?= number_format($invoice['total_paid'], 2) ?></p>
                                    <p><strong>Saldo Pendiente:</strong> $<?= number_format($invoice['remaining_amount'], 2) ?></p>
                                    <?php if(isset($payment_info) && !empty($payment_info['payments'])): ?>
                                    <div class="mt-2">
                                        <strong>Pagos Anteriores:</strong>
                                        <ul class="mt-1">
                                        <?php foreach($payment_info['payments'] as $prev_payment): ?>
                                            <li>
                                                <?= date('d/m/Y', strtotime($prev_payment['payment_date'])) ?> - 
                                                $<?= number_format($prev_payment['amount'], 2) ?> 
                                                (<?= esc($prev_payment['payment_method']) ?>)
                                            </li>
                                        <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Si no se pasa una factura específica, mostrar dropdown para seleccionar -->
                        <div class="mb-3">
                            <label for="invoice_id" class="form-label">Cuenta por Cobrar *</label>
                            <select name="invoice_id" id="invoice_id" class="form-select" required>
                                <option value="">Seleccione una cuenta por cobrar</option>
                                <?php if(!empty($invoices)): ?>
                                    <?php foreach ($invoices as $inv): ?>
                                        <option value="<?= $inv['id'] ?>" <?= old('invoice_id') == $inv['id'] ? 'selected' : '' ?>>
                                            <?= esc($inv['invoice_number']) ?> - 
                                            <?= esc($inv['client_name']) ?> - 
                                            Saldo: $<?= number_format($inv['remaining_amount'], 2) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Monto del Pago *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   value="<?= old('amount', isset($invoice) ? $invoice['remaining_amount'] : '') ?>" 
                                   required step="0.01" min="0.01" 
                                   <?= isset($invoice) ? 'max="' . $invoice['remaining_amount'] . '"' : '' ?>>
                        </div>
                        <?php if (isset($invoice)): ?>
                        <div class="form-text">Máximo: $<?= number_format($invoice['remaining_amount'], 2) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Método de Pago *</label>
                        <select name="payment_method" id="payment_method" class="form-select" required>
                            <option value="">Seleccione un método</option>
                            <option value="cash" <?= old('payment_method') === 'cash' ? 'selected' : '' ?>>Efectivo</option>
                            <option value="transfer" <?= old('payment_method') === 'transfer' ? 'selected' : '' ?>>Transferencia</option>
                            <option value="check" <?= old('payment_method') === 'check' ? 'selected' : '' ?>>Cheque</option>
                            <option value="credit_card" <?= old('payment_method') === 'credit_card' ? 'selected' : '' ?>>Tarjeta de Crédito</option>
                            <option value="debit_card" <?= old('payment_method') === 'debit_card' ? 'selected' : '' ?>>Tarjeta de Débito</option>
                            <option value="qr_code" <?= old('payment_method') === 'qr_code' ? 'selected' : '' ?>>Código QR</option>
                            <option value="other" <?= old('payment_method') === 'other' ? 'selected' : '' ?>>Otro</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reference_code" class="form-label">Código de Referencia (Opcional)</label>
                        <input type="text" class="form-control" id="reference_code" name="reference_code" 
                               value="<?= old('reference_code') ?>" maxlength="100">
                        <div class="form-text">Número de comprobante, autorización, etc.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notas (Opcional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= old('notes') ?></textarea>
                    </div>
                    
                    <!-- Campos ocultos para la geolocalización -->
                    <input type="hidden" id="latitude" name="latitude" value="<?= old('latitude') ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?= old('longitude') ?>">
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <?php if (!empty($invoice)): ?>
                            <a href="<?= site_url('invoices/view/' . $invoice['id']) ?>" class="btn btn-secondary">Cancelar</a>
                        <?php else: ?>
                            <a href="<?= site_url('payments') ?>" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Registrar Pago</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                Información
            </div>
            <div class="card-body">
                <p>Complete todos los campos requeridos marcados con *.</p>
                <p>Puede registrar pagos parciales para una factura.</p>
                <p>El saldo pendiente se calcula automáticamente considerando pagos anteriores.</p>
                <p>Cuando el total pagado iguale o supere el monto de la factura, ésta se marcará como pagada.</p>
                <p>El pago será registrado con la fecha y hora actual.</p>
                <hr>
                <div class="mb-3">
                    <div id="location-status" class="alert alert-warning">
                        Obteniendo ubicación...
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Obtener la ubicación actual
    document.addEventListener('DOMContentLoaded', function() {
        // Para el selector de organizaciones (superadmin)
        const organizationSelect = document.getElementById('organization_id');
        if (organizationSelect) {
            organizationSelect.addEventListener('change', function() {
                const organizationId = this.value;
                if (organizationId) {
                    window.location.href = '<?= site_url('payments/create') ?>?organization_id=' + organizationId;
                }
            });
        }
        
        // Geolocalización
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    // Éxito
                    document.getElementById('latitude').value = position.coords.latitude;
                    document.getElementById('longitude').value = position.coords.longitude;
                    document.getElementById('location-status').className = 'alert alert-success';
                    document.getElementById('location-status').textContent = 'Ubicación obtenida correctamente.';
                },
                function(error) {
                    // Error
                    document.getElementById('location-status').className = 'alert alert-danger';
                    let errorMessage = 'Error al obtener la ubicación: ';
                    
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage += 'Permiso denegado.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage += 'Posición no disponible.';
                            break;
                        case error.TIMEOUT:
                            errorMessage += 'Tiempo de espera agotado.';
                            break;
                        default:
                            errorMessage += 'Error desconocido.';
                    }
                    
                    document.getElementById('location-status').textContent = errorMessage;
                }
            );
        } else {
            document.getElementById('location-status').className = 'alert alert-danger';
            document.getElementById('location-status').textContent = 'Geolocalización no soportada en este navegador.';
        }
    });
</script>
<?= $this->endSection() ?>