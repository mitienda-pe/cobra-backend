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
                                    <p><strong>Monto Total:</strong> S/ <?= number_format($invoice['amount'], 2) ?></p>
                                    <p><strong>Vencimiento:</strong> <?= date('d/m/Y', strtotime($invoice['due_date'])) ?></p>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-12">
                                    <p><strong>Total Pagado:</strong> S/ <?= number_format($invoice['total_paid'], 2) ?></p>
                                    <p><strong>Saldo Pendiente:</strong> S/ <?= number_format($invoice['remaining_amount'], 2) ?></p>
                                    <?php if (isset($payment_info) && !empty($payment_info['payments'])): ?>
                                        <div class="mt-2">
                                            <strong>Pagos Anteriores:</strong>
                                            <ul class="mt-1">
                                                <?php foreach ($payment_info['payments'] as $prev_payment): ?>
                                                    <li>
                                                        <?= date('d/m/Y', strtotime($prev_payment['payment_date'])) ?> -
                                                        S/ <?= number_format($prev_payment['amount'], 2) ?>
                                                        (<?= esc($prev_payment['payment_method']) ?>)
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($instalments) && !empty($instalments)): ?>
                            <div class="mb-3">
                                <label for="instalment_id" class="form-label">Seleccionar Cuota</label>
                                <select class="form-select" id="instalment_id" name="instalment_id">
                                    <option value="">Pago general (sin asociar a cuota específica)</option>
                                    <?php foreach ($instalments as $instalment): ?>
                                        <!-- DEBUG: Cuota <?= $instalment['number'] ?> - Status: <?= $instalment['status'] ?> - Remaining: <?= $instalment['remaining_amount'] ?> - Paid: <?= $instalment['paid_amount'] ?? 'N/A' ?> -->
                                        <?php if ($instalment['status'] !== 'paid' && $instalment['remaining_amount'] > 0): ?>
                                            <option value="<?= $instalment['id'] ?>"
                                                data-amount="<?= $instalment['remaining_amount'] ?>"
                                                <?= (isset($_GET['instalment_id']) && $_GET['instalment_id'] == $instalment['id']) ? 'selected' : '' ?>>
                                                Cuota <?= $instalment['number'] ?> -
                                                Vence: <?= date('d/m/Y', strtotime($instalment['due_date'])) ?> -
                                                Pendiente: S/ <?= number_format($instalment['remaining_amount'], 2) ?>
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
                                <?php if (!empty($invoice)): ?>
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
                            <option value="ligo_qr" <?= old('payment_method') == 'ligo_qr' ? 'selected' : '' ?>>QR</option>
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

<!-- Modal para mostrar el QR de Ligo -->
<div class="modal fade" id="ligoQrModal" tabindex="-1" aria-labelledby="ligoQrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="ligoQrModalLabel">Pago con QR</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center" id="ligoQrModalBody">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p>Generando código QR...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="regenerateQrBtn">Regenerar QR</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<!-- Include jQuery first, before any plugins that depend on it -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // Manejar cambio en el método de pago para mostrar el modal de QR si se selecciona
        $('#payment_method').on('change', function() {
            if ($(this).val() === 'ligo_qr') {
                // Prevenir el envío del formulario al seleccionar QR
                event.preventDefault();

                // Verificar si hay una factura seleccionada
                let invoiceId = $('input[name="invoice_id"]').val();
                let instalmentId = $('select[name="instalment_id"]').val();

                if (invoiceId) {
                    console.log('Mostrando modal para factura ID:', invoiceId);
                    // Mostrar el modal con spinner
                    $('#ligoQrModal').modal('show');

                    // Cargar el QR via AJAX
                    loadLigoQR(invoiceId, instalmentId);
                } else if ($('#invoice_search').val()) {
                    // Si estamos en la vista de búsqueda y hay una factura seleccionada
                    invoiceId = $('#invoice_search').val();
                    console.log('Mostrando modal para factura seleccionada:', invoiceId);
                    $('#ligoQrModal').modal('show');
                    loadLigoQR(invoiceId, null);
                } else {
                    alert('Por favor, seleccione una factura antes de elegir el método de pago QR.');
                    $(this).val(''); // Resetear la selección
                }

                // Evitar que el formulario se envíe automáticamente
                return false;
            }
        });

        // Agregar un manejador para el envío del formulario
        $('#payment-form').on('submit', function(e) {
            // Si el método de pago es QR, prevenir el envío del formulario
            if ($('#payment_method').val() === 'ligo_qr') {
                e.preventDefault();
                return false;
            }
        });

        // Función para cargar el QR de Ligo via AJAX
        function loadLigoQR(invoiceId, instalmentId) {
            let url = '<?= site_url('payment/ligo/ajax-qr') ?>/' + invoiceId;
            if (instalmentId) {
                url += '/' + instalmentId;
            }

            console.log('Cargando QR desde URL:', url);

            // Mostrar spinner mientras carga
            $('#ligoQrModalBody').html(`
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p>Generando código QR...</p>
        `);

            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Respuesta del servidor:', response);

                    // Siempre mostrar el QR si está disponible, incluso si hay errores
                    if (response.qr_image_url) {
                        // Actualizar el contenido del modal con el QR
                        let modalContent = `
                        <h5 class="card-title">Factura #${response.invoice_number}</h5>
                        <p class="card-text">Monto a pagar: ${response.currency} ${response.amount}</p>
                        <div class="my-4">
                            <img src="${response.qr_image_url}" alt="QR Code" class="img-fluid" style="max-width: 250px;">
                        </div>
                        <p class="text-muted">Escanea el código QR con Yape o Plin para realizar el pago</p>
                    `;

                        // Mostrar mensaje de demo si es necesario
                        if (response.is_demo) {
                            modalContent += `
                            <div class="alert alert-warning">
                                <p><strong>Modo Demostración</strong></p>
                                <p class="mb-0">Este es un QR de demostración. Para habilitar pagos reales con Ligo, configure las credenciales en la sección de configuración de la organización.</p>
                            </div>
                        `;
                        }

                        // Mostrar fecha de expiración si está disponible
                        if (response.expiration) {
                            modalContent += `
                            <p class="text-muted">
                                <small>Este código QR expira el: ${response.expiration}</small>
                            </p>
                        `;
                        }

                        // Mostrar mensaje de error si hay uno, pero seguimos mostrando el QR
                        if (response.error_message) {
                            modalContent += `
                            <div class="alert alert-info">
                                <p class="mb-0 small">${response.error_message}</p>
                            </div>
                        `;
                        }

                        $('#ligoQrModalBody').html(modalContent);
                    } else if (!response.success) {
                        // Mostrar error cuando no hay QR y la respuesta indica error
                        $('#ligoQrModalBody').html(`
                        <div class="alert alert-danger">
                            <h5><i class="bi bi-exclamation-triangle-fill"></i> Error</h5>
                            <p>${response.error_message || 'No se pudo generar el código QR.'}</p>
                            <p class="mb-0 small">Si el problema persiste, por favor contacte al administrador del sistema.</p>
                        </div>
                    `);
                    } else {
                        // Caso inesperado: éxito pero sin QR
                        $('#ligoQrModalBody').html(`
                        <div class="alert alert-warning">
                            <h5><i class="bi bi-exclamation-triangle-fill"></i> Advertencia</h5>
                            <p>Se procesó la solicitud pero no se recibió un código QR. Intente nuevamente.</p>
                        </div>
                    `);
                    }

                    // Configurar el botón de regenerar QR
                    $('#regenerateQrBtn').off('click').on('click', function() {
                        $('#ligoQrModalBody').html(`
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p>Regenerando código QR...</p>
                    `);
                        loadLigoQR(invoiceId, instalmentId);
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', error);
                    console.error('Estado:', status);
                    console.error('Respuesta:', xhr.responseText);

                    // Mostrar error
                    $('#ligoQrModalBody').html(`
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-exclamation-triangle-fill"></i> Error</h5>
                        <p>Error al comunicarse con el servidor: ${error}</p>
                        <p class="mb-0 small">Si el problema persiste, por favor contacte al administrador del sistema.</p>
                    </div>
                `);
                }
            });
        }

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
                        <p><strong>Monto Total:</strong> S/ ${data.amount}</p>
                        <p><strong>Saldo Pendiente:</strong> S/ ${data.remaining}</p>
                    </div>
                </div>
            </div>
        `;

            $('#invoice_details').html(detailsHtml).show();

            // Actualizar el monto máximo del pago
            $('#amount').attr('max', data.remaining);

            // Actualizar el símbolo de la moneda
            $('.input-group-text').text('S/');
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