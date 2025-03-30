<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Importar Cuentas por Cobrar<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Importar Cuentas por Cobrar</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= site_url('invoices') ?>">Cuentas por Cobrar</a></li>
                <li class="breadcrumb-item active">Importar</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form action="<?= site_url('invoices/import') ?>" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Archivo CSV *</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" required accept=".csv">
                    </div>
                    
                    <div class="mb-3">
                        <label for="default_num_instalments" class="form-label">Número de cuotas por defecto</label>
                        <select class="form-select" id="default_num_instalments" name="default_num_instalments">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                        <div class="form-text">Número de cuotas que se crearán para cada factura importada.</div>
                    </div>
                    
                    <div class="mb-3" id="default_instalment_interval_container">
                        <label for="default_instalment_interval" class="form-label">Intervalo entre cuotas (días)</label>
                        <input type="number" class="form-control" id="default_instalment_interval" name="default_instalment_interval" value="30" min="1" max="365">
                        <div class="form-text">Días entre cada cuota. Solo aplica cuando el número de cuotas es mayor a 1.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="override_csv_instalments" name="override_csv_instalments" value="1">
                            <label class="form-check-label" for="override_csv_instalments">
                                Usar columnas de cuotas del CSV si están presentes
                            </label>
                            <div class="form-text">Si el CSV contiene las columnas 'num_instalments' e 'instalment_interval', se usarán esos valores en lugar de los valores por defecto.</div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?= site_url('invoices') ?>" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Importar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                Formato del Archivo CSV
            </div>
            <div class="card-body">
                <p>El archivo CSV debe contener las siguientes columnas:</p>
                <ul>
                    <li><strong>document_number</strong>: Número de documento del cliente (RUC/CI)</li>
                    <li><strong>invoice_number</strong>: Número de factura</li>
                    <li><strong>concept</strong>: Concepto de la factura</li>
                    <li><strong>amount</strong>: Monto (usar punto como separador decimal)</li>
                    <li><strong>currency</strong>: Moneda (PEN o USD)</li>
                    <li><strong>due_date</strong>: Fecha de vencimiento (formato YYYY-MM-DD)</li>
                    <li><strong>external_id</strong>: ID externo (opcional)</li>
                    <li><strong>notes</strong>: Notas adicionales (opcional)</li>
                    <li><strong>num_instalments</strong>: Número de cuotas (opcional)</li>
                    <li><strong>instalment_interval</strong>: Intervalo entre cuotas en días (opcional)</li>
                </ul>
                <p>Ejemplo:</p>
                <pre>document_number,invoice_number,concept,amount,currency,due_date,external_id,notes,num_instalments,instalment_interval
1234567890,A-001,Servicio de consultoría,1500.00,PEN,2025-04-15,EXT-001,Notas adicionales,3,30
9876543210,B-002,Venta de productos,2000.00,USD,2025-04-30,,,1,</pre>
                <p>Notas:</p>
                <ul>
                    <li>La primera fila debe contener los nombres de las columnas.</li>
                    <li>El cliente debe existir en el sistema con el mismo número de documento.</li>
                    <li>El estado inicial de todas las facturas importadas será "Pendiente".</li>
                </ul>
                <p><a href="#" class="btn btn-sm btn-outline-primary">Descargar plantilla</a></p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const numInstalments = document.getElementById('default_num_instalments');
        const intervalContainer = document.getElementById('default_instalment_interval_container');
        
        // Mostrar/ocultar el campo de intervalo según el número de cuotas
        function toggleIntervalField() {
            if (parseInt(numInstalments.value) > 1) {
                intervalContainer.style.display = 'block';
            } else {
                intervalContainer.style.display = 'none';
            }
        }
        
        // Ejecutar al cargar la página
        toggleIntervalField();
        
        // Ejecutar cuando cambie el número de cuotas
        numInstalments.addEventListener('change', toggleIntervalField);
    });
</script>
<?= $this->endSection() ?>