<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Registrar Pago<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<?= $this->endSection() ?>

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
                <form action="<?= site_url('payments/create') ?>" method="post" id="payment-form">
                    <?= csrf_field() ?>
                    
                    <?php if (!empty($invoice)): ?>
                        <!-- Si se pasa una factura específica, mostrar su información -->
                        <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                        
                        <div class="alert alert-info">
                            <h5>Información de la Cuenta por Cobrar</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Factura:</strong> <?= esc($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A') ?></p>
                                    <p><strong>Cliente:</strong> <?= esc($client['business_name']) ?></p>
                                    <p><strong>Documento:</strong> <?= esc($client['document_number']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Concepto:</strong> <?= esc($invoice['concept']) ?></p>
                                    <p><strong>Monto Total:</strong> <?= $invoice['currency'] === 'PEN' ? 'S/ ' : '$ ' ?><?= number_format($invoice['amount'], 2) ?></p>
                                    <p><strong>Vencimiento:</strong> <?= date('d/m/Y', strtotime($invoice['due_date'])) ?></p>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-12">
                                    <p><strong>Total Pagado:</strong> <?= $invoice['currency'] === 'PEN' ? 'S/ ' : '$ ' ?><?= number_format($invoice['total_paid'], 2) ?></p>
                                    <p><strong>Saldo Pendiente:</strong> <?= $invoice['currency'] === 'PEN' ? 'S/ ' : '$ ' ?><?= number_format($invoice['remaining_amount'], 2) ?></p>
                                    <?php if(isset($payment_info) && !empty($payment_info['payments'])): ?>
                                    <div class="mt-2">
                                        <strong>Pagos Anteriores:</strong>
                                        <ul class="mt-1">
                                        <?php foreach($payment_info['payments'] as $prev_payment): ?>
                                            <li>
                                                <?= date('d/m/Y', strtotime($prev_payment['payment_date'])) ?> - 
                                                <?= $invoice['currency'] === 'PEN' ? 'S/ ' : '$ ' ?><?= number_format($prev_payment['amount'], 2) ?> 
                                                (<?= esc($prev_payment['payment_method']) ?>)
                                            </li>
                                        <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if(isset($instalments) && !empty($instalments)): ?>
                        <div class="mb-3">
                            <label for="instalment_id" class="form-label">Seleccionar Cuota</label>
                            <select class="form-select" id="instalment_id" name="instalment_id">
                                <option value="">Pago general (sin asociar a cuota específica)</option>
                                <?php foreach($instalments as $instalment): ?>
                                    <?php if($instalment['status'] !== 'paid' && $instalment['remaining_amount'] > 0): ?>
                                    <option value="<?= $instalment['id'] ?>" 
                                            data-amount="<?= $instalment['remaining_amount'] ?>"
                                            <?= (isset($_GET['instalment_id']) && $_GET['instalment_id'] == $instalment['id']) ? 'selected' : '' ?>>
                                        Cuota <?= $instalment['number'] ?> - 
                                        Vence: <?= date('d/m/Y', strtotime($instalment['due_date'])) ?> - 
                                        Pendiente: <?= $invoice['currency'] === 'PEN' ? 'S/ ' : '$ ' ?><?= number_format($instalment['remaining_amount'], 2) ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Si selecciona una cuota específica, el pago se asociará a esa cuota.</div>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Campo de búsqueda de facturas con autocompletado -->
                        <div class="mb-3">
                            <label for="invoice_search" class="form-label">Buscar Factura *</label>
                            <select class="form-select" id="invoice_search" name="invoice_id" required>
                                <option value="">Buscar por número de factura o cliente...</option>
                            </select>
                            <div id="invoice_details" class="mt-3" style="display: none;">
                                <!-- Los detalles de la factura se cargarán aquí dinámicamente -->
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Monto del Pago *</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= !empty($invoice) && $invoice['currency'] === 'USD' ? '$' : 'S/' ?></span>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" required 
                                   value="<?= old('amount') ?>" min="0.01" 
                                   <?php if(!empty($invoice)): ?>
                                   max="<?= $invoice['remaining_amount'] ?>"
                                   <?php endif; ?>>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Método de Pago *</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">Seleccione un método de pago</option>
                            <option value="cash" <?= old('payment_method') == 'cash' ? 'selected' : '' ?>>Efectivo</option>
                            <option value="transfer" <?= old('payment_method') == 'transfer' ? 'selected' : '' ?>>Transferencia</option>
                            <option value="deposit" <?= old('payment_method') == 'deposit' ? 'selected' : '' ?>>Depósito</option>
                            <option value="check" <?= old('payment_method') == 'check' ? 'selected' : '' ?>>Cheque</option>
                            <option value="card" <?= old('payment_method') == 'card' ? 'selected' : '' ?>>Tarjeta</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reference_code" class="form-label">Código de Referencia</label>
                        <input type="text" class="form-control" id="reference_code" name="reference_code" 
                               value="<?= old('reference_code') ?>" maxlength="100">
                        <div class="form-text">Número de operación, voucher o referencia del pago</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notas</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= old('notes') ?></textarea>
                    </div>
                    
                    <!-- Campos ocultos para la geolocalización -->
                    <input type="hidden" id="latitude" name="latitude">
                    <input type="hidden" id="longitude" name="longitude">
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Registrar Pago</button>
                        <a href="<?= site_url('payments') ?>" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Información</h5>
            </div>
            <div class="card-body">
                <p>Complete todos los campos marcados con (*). El sistema registrará automáticamente la fecha y hora del pago.</p>
                <p>Si el pago es en efectivo, asegúrese de verificar el monto antes de registrarlo.</p>
                <p>Para pagos con transferencia o depósito, ingrese el número de operación en el campo de referencia.</p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Inicializar Select2 para la búsqueda de facturas
    $('#invoice_search').select2({
        theme: 'bootstrap-5',
        ajax: {
            url: '<?= site_url('payments/search-invoices') ?>',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    term: params.term
                };
            },
            processResults: function(data) {
                return {
                    results: data.results
                };
            },
            cache: true
        },
        placeholder: 'Buscar por número de factura o cliente...',
        minimumInputLength: 2,
        language: {
            inputTooShort: function() {
                return 'Por favor ingrese 2 o más caracteres';
            },
            noResults: function() {
                return 'No se encontraron resultados';
            },
            searching: function() {
                return 'Buscando...';
            }
        }
    }).on('select2:select', function(e) {
        var data = e.params.data;
        
        // Actualizar el div de detalles de la factura
        var detailsHtml = `
            <div class="alert alert-info">
                <h5>Información de la Cuenta por Cobrar</h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Factura:</strong> ${data.number || data.invoice_number || 'N/A'}</p>
                        <p><strong>Cliente:</strong> ${data.client_name}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Monto Total:</strong> ${data.currency === 'PEN' ? 'S/ ' : '$ '}${data.amount}</p>
                        <p><strong>Saldo Pendiente:</strong> ${data.currency === 'PEN' ? 'S/ ' : '$ '}${data.remaining}</p>
                    </div>
                </div>
            </div>
        `;
        
        $('#invoice_details').html(detailsHtml).show();
        
        // Actualizar el monto máximo del pago
        $('#amount').attr('max', data.remaining);
        
        // Actualizar el símbolo de la moneda
        $('.input-group-text').text(data.currency === 'PEN' ? 'S/' : '$');
    });
    
    // Manejar cambio de cuota seleccionada
    $('#instalment_id').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        if (selectedOption.val() !== '') {
            var maxAmount = selectedOption.data('amount');
            $('#amount').attr('max', maxAmount);
            $('#amount').val(maxAmount);
        } else {
            var invoiceRemaining = <?= !empty($invoice) ? $invoice['remaining_amount'] : 0 ?>;
            $('#amount').attr('max', invoiceRemaining);
        }
    });
    
    // Inicializar el valor del monto si hay una cuota seleccionada
    if ($('#instalment_id').length && $('#instalment_id').val() !== '') {
        $('#instalment_id').trigger('change');
    }

    // Obtener la ubicación actual
    if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition(function(position) {
            $('#latitude').val(position.coords.latitude);
            $('#longitude').val(position.coords.longitude);
        });
    }
    
    // Validación del formulario
    $('#payment-form').on('submit', function(e) {
        var amount = parseFloat($('#amount').val());
        var max = parseFloat($('#amount').attr('max'));
        
        if (amount > max) {
            e.preventDefault();
            alert('El monto del pago no puede ser mayor al saldo pendiente.');
            return false;
        }
    });
});
</script>
<?= $this->endSection() ?>