<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Importar Clientes<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Importar Clientes (CSV)</h3>
                    <a href="<?= site_url('clients') ?>" class="btn btn-secondary">Volver</a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5>Instrucciones</h5>
                    <p>Suba un archivo CSV con los siguientes campos:</p>
                    <ol>
                        <li>Nombre Comercial (obligatorio)</li>
                        <li>Razón Social (obligatorio)</li>
                        <li>RUC/Documento (obligatorio y debe ser único en su organización)</li>
                        <li>Nombre de Contacto (opcional)</li>
                        <li>Teléfono (opcional)</li>
                        <li>Dirección (opcional)</li>
                        <li>Ubigeo (opcional)</li>
                        <li>Código Postal (opcional)</li>
                        <li>Latitud (opcional)</li>
                        <li>Longitud (opcional)</li>
                        <li>ID Externo (opcional)</li>
                        <li>UUID Cartera (opcional, debe ser un UUID válido de una cartera)</li>
                    </ol>
                    <p><strong>Notas importantes:</strong></p>
                    <ul>
                        <li>La primera fila debe contener los nombres de las columnas.</li>
                        <li>Los clientes que ya existan en el sistema (con el mismo número de documento) serán omitidos.</li>
                        <li>Si se incluye un UUID de cartera, el cliente será asignado automáticamente a esa cartera.</li>
                        <li>Todos los clientes se crearán con estado "Activo".</li>
                    </ul>
                </div>
                
                <form id="import-form" action="<?= site_url('clients/import') ?>" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Archivo CSV *</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" required accept=".csv">
                    </div>
                    
                    <!-- Organization Context (automatically handled) -->
                    <?php if ($auth->organizationId()): ?>
                        <input type="hidden" name="organization_id" value="<?= $auth->organizationId() ?>">
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Importar Clientes
                    </button>
                    <a href="<?= site_url('clients') ?>" class="btn btn-secondary">Cancelar</a>
                </form>
                
                <div class="mt-4">
                    <h5>Ejemplo de CSV</h5>
                    <pre>nombre_comercial,razon_social,documento,contacto,telefono,direccion,ubigeo,codigo_postal,latitud,longitud,id_externo,portfolio_uuid
Empresa ABC,Empresa ABC S.A.,20345678901,Juan Pérez,987654321,"Av. Principal 123, Lima",150101,15001,-12.046374,-77.042793,EXT123,550e8400-e29b-41d4-a716-446655440000
Empresa XYZ,Corporación XYZ S.A.C.,20456789012,María Gómez,987654322,"Jr. Secundario 456, Lima",150102,15002,-12.046375,-77.042794,EXT124,550e8400-e29b-41d4-a716-446655440001</pre>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>