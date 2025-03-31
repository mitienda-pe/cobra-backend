<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Crear Cuotas para Factura #<?= $invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A' ?><?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .custom-instalments-container {
        display: none;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid px-4">
    <h1 class="mt-4">Crear Cuotas para Factura #<?= $invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A' ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('invoices') ?>">Facturas</a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('invoices/view/' . $invoice['uuid']) ?>">Factura #<?= $invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A' ?></a></li>
        <li class="breadcrumb-item"><a href="<?= site_url('invoice/' . $invoice['id'] . '/instalments') ?>">Cuotas</a></li>
        <li class="breadcrumb-item active">Crear</li>
    </ol>
    
    <?php if (session()->getFlashdata('errors')) : ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach (session()->getFlashdata('errors') as $error) : ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (session()->getFlashdata('error')) : ?>
        <div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-info-circle me-1"></i>
            Información de la Factura
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Cliente:</strong> <?= $client['business_name'] ?></p>
                    <p><strong>Documento:</strong> <?= $client['document_number'] ?></p>
                    <p><strong>Fecha de Emisión:</strong> <?= date('d/m/Y', strtotime($invoice['issue_date'])) ?></p>
                    <p><strong>Fecha de Vencimiento:</strong> <?= date('d/m/Y', strtotime($invoice['due_date'])) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Monto Total:</strong> <?= $invoice['currency'] ?> <?= number_format($invoice['amount'], 2) ?></p>
                    <p><strong>Estado:</strong> 
                        <span class="badge bg-<?= $invoice['status'] === 'paid' ? 'success' : ($invoice['status'] === 'pending' ? 'warning' : 'danger') ?>">
                            <?= $invoice['status'] === 'paid' ? 'Pagada' : ($invoice['status'] === 'pending' ? 'Pendiente' : 'Cancelada') ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-plus me-1"></i>
            Crear Cuotas
        </div>
        <div class="card-body">
            <form action="<?= site_url('invoice/instalments/store') ?>" method="post" id="instalmentForm">
                <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="instalment_type" class="form-label">Tipo de Cuotas</label>
                        <select name="instalment_type" id="instalment_type" class="form-select" required>
                            <option value="equal">Cuotas Iguales</option>
                            <option value="custom">Cuotas Personalizadas</option>
                        </select>
                    </div>
                </div>
                
                <div class="equal-instalments-container">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="num_instalments" class="form-label">Número de Cuotas</label>
                            <input type="number" name="num_instalments" id="num_instalments" class="form-control" min="1" max="36" value="<?= old('num_instalments', 3) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="first_due_date" class="form-label">Fecha Primera Cuota</label>
                            <input type="text" name="first_due_date" id="first_due_date" class="form-control date-picker" value="<?= old('first_due_date', date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="interval" class="form-label">Intervalo (días)</label>
                            <input type="number" name="interval" id="interval" class="form-control" min="1" max="90" value="<?= old('interval', 30) ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <p class="mb-0">
                                    <strong>Monto por cuota:</strong> <span id="instalment_amount"><?= $invoice['currency'] ?> <?= number_format($invoice['amount'] / 3, 2) ?></span>
                                    <br>
                                    <small class="text-muted">El monto de la última cuota puede variar ligeramente para ajustar el total.</small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="custom-instalments-container">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="custom_num_instalments" class="form-label">Número de Cuotas</label>
                            <input type="number" name="custom_num_instalments" id="custom_num_instalments" class="form-control" min="1" max="36" value="<?= old('custom_num_instalments', 3) ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="custom_first_due_date" class="form-label">Fecha Primera Cuota</label>
                            <input type="text" name="custom_first_due_date" id="custom_first_due_date" class="form-control date-picker" value="<?= old('custom_first_due_date', date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" id="generate_custom_instalments" class="btn btn-primary">Generar Campos</button>
                        </div>
                    </div>
                    
                    <div id="custom_instalments_fields" class="mb-3">
                        <!-- Los campos de cuotas personalizadas se generarán aquí -->
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <p class="mb-0">
                                    <strong>Monto total de cuotas:</strong> <span id="total_custom_amount"><?= $invoice['currency'] ?> 0.00</span>
                                    <br>
                                    <strong>Monto de factura:</strong> <?= $invoice['currency'] ?> <?= number_format($invoice['amount'], 2) ?>
                                    <br>
                                    <strong>Diferencia:</strong> <span id="amount_difference"><?= $invoice['currency'] ?> <?= number_format($invoice['amount'], 2) ?></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Guardar Cuotas</button>
                        <a href="<?= site_url('invoice/' . $invoice['id'] . '/instalments') ?>" class="btn btn-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
    $(function() {
        // Inicializar datepicker
        $(".date-picker").flatpickr({
            locale: "es",
            dateFormat: "Y-m-d",
            allowInput: true
        });
        
        // Cambiar tipo de cuotas
        $("#instalment_type").change(function() {
            if ($(this).val() === "equal") {
                $(".equal-instalments-container").show();
                $(".custom-instalments-container").hide();
            } else {
                $(".equal-instalments-container").hide();
                $(".custom-instalments-container").show();
            }
        });
        
        // Calcular monto por cuota (cuotas iguales)
        $("#num_instalments").change(function() {
            const totalAmount = <?= $invoice['amount'] ?>;
            const numInstalments = parseInt($(this).val()) || 1;
            const instalmentAmount = (totalAmount / numInstalments).toFixed(2);
            
            $("#instalment_amount").text("<?= $invoice['currency'] ?> " + instalmentAmount);
        });
        
        // Generar campos para cuotas personalizadas
        $("#generate_custom_instalments").click(function() {
            const numInstalments = parseInt($("#custom_num_instalments").val()) || 0;
            const firstDueDate = $("#custom_first_due_date").val();
            const totalAmount = <?= $invoice['amount'] ?>;
            const currency = "<?= $invoice['currency'] ?>";
            
            if (numInstalments <= 0) {
                alert("Por favor ingrese un número válido de cuotas");
                return;
            }
            
            if (!firstDueDate) {
                alert("Por favor seleccione la fecha de la primera cuota");
                return;
            }
            
            // Calcular monto sugerido por cuota
            const suggestedAmount = (totalAmount / numInstalments).toFixed(2);
            
            // Generar campos
            let html = '<div class="table-responsive"><table class="table table-bordered">';
            html += '<thead><tr><th>N° Cuota</th><th>Monto</th><th>Fecha Vencimiento</th><th>Notas</th></tr></thead>';
            html += '<tbody>';
            
            for (let i = 1; i <= numInstalments; i++) {
                // Calcular fecha de vencimiento
                let dueDate = new Date(firstDueDate);
                dueDate.setDate(dueDate.getDate() + ((i - 1) * 30)); // 30 días entre cuotas por defecto
                
                const formattedDate = dueDate.toISOString().split('T')[0];
                
                html += '<tr>';
                html += '<td>' + i + '</td>';
                html += '<td><input type="number" name="amount_' + i + '" class="form-control custom-amount" step="0.01" min="0.01" value="' + suggestedAmount + '" required></td>';
                html += '<td><input type="text" name="due_date_' + i + '" class="form-control date-picker" value="' + formattedDate + '" required></td>';
                html += '<td><input type="text" name="notes_' + i + '" class="form-control" placeholder="Notas (opcional)"></td>';
                html += '</tr>';
            }
            
            html += '</tbody></table></div>';
            
            // Mostrar campos
            $("#custom_instalments_fields").html(html);
            
            // Reinicializar datepicker en los nuevos campos
            $("#custom_instalments_fields .date-picker").flatpickr({
                locale: "es",
                dateFormat: "Y-m-d",
                allowInput: true
            });
            
            // Actualizar totales al cambiar montos
            $(".custom-amount").on("input", updateTotals);
            
            // Actualizar totales iniciales
            updateTotals();
        });
        
        // Función para actualizar totales de cuotas personalizadas
        function updateTotals() {
            const totalAmount = <?= $invoice['amount'] ?>;
            let customTotal = 0;
            
            $(".custom-amount").each(function() {
                customTotal += parseFloat($(this).val()) || 0;
            });
            
            const difference = totalAmount - customTotal;
            
            $("#total_custom_amount").text("<?= $invoice['currency'] ?> " + customTotal.toFixed(2));
            $("#amount_difference").text("<?= $invoice['currency'] ?> " + difference.toFixed(2));
            
            // Cambiar color según la diferencia
            if (Math.abs(difference) < 0.01) {
                $("#amount_difference").removeClass("text-danger").addClass("text-success");
            } else {
                $("#amount_difference").removeClass("text-success").addClass("text-danger");
            }
        }
        
        // Validar formulario antes de enviar
        $("#instalmentForm").submit(function(e) {
            const instalmentType = $("#instalment_type").val();
            
            if (instalmentType === "custom") {
                const totalAmount = <?= $invoice['amount'] ?>;
                let customTotal = 0;
                
                $(".custom-amount").each(function() {
                    customTotal += parseFloat($(this).val()) || 0;
                });
                
                const difference = Math.abs(totalAmount - customTotal);
                
                if (difference > 0.01) {
                    e.preventDefault();
                    alert("El total de las cuotas debe ser igual al monto de la factura. Diferencia actual: <?= $invoice['currency'] ?> " + difference.toFixed(2));
                }
            }
        });
    });
</script>
<?= $this->endSection() ?>
