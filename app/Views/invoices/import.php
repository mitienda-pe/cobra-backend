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
                </ul>
                <p>Ejemplo:</p>
                <pre>document_number,invoice_number,concept,amount,currency,due_date,external_id,notes
1234567890,A-001,Servicio de consultoría,1500.00,PEN,2025-04-15,EXT-001,Notas adicionales
9876543210,B-002,Venta de productos,2000.00,USD,2025-04-30,,</pre>
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