<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Nuevo Webhook<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Nuevo Webhook</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= site_url('webhooks') ?>">Webhooks</a></li>
                <li class="breadcrumb-item active">Nuevo Webhook</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form action="<?= site_url('webhooks/create') ?>" method="post">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?= old('name') ?>" required minlength="3" maxlength="100">
                        <div class="form-text">Un nombre descriptivo para este webhook (ej. "Notificación ERP").</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="url" class="form-label">URL *</label>
                        <input type="url" class="form-control" id="url" name="url" 
                               value="<?= old('url') ?>" required placeholder="https://ejemplo.com/api/webhook">
                        <div class="form-text">La URL completa a la que se enviarán las notificaciones.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Eventos *</label>
                        <div class="form-text mb-2">Seleccione los eventos que activarán este webhook.</div>
                        
                        <div class="row">
                            <?php foreach ($events as $value => $label): ?>
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="events[]" 
                                               value="<?= $value ?>" id="event_<?= $value ?>"
                                               <?= is_array(old('events')) && in_array($value, old('events')) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="event_<?= $value ?>">
                                            <?= $label ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                               <?= old('is_active') ? 'checked' : '' ?> checked>
                        <label class="form-check-label" for="is_active">Activo</label>
                        <div class="form-text">Desmarque para desactivar temporalmente este webhook sin eliminarlo.</div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?= site_url('webhooks') ?>" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar</button>
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
                <p>Los webhooks permiten notificar a sistemas externos cuando ocurren eventos en su aplicación.</p>
                
                <p>Una vez creado, el sistema asignará automáticamente una clave secreta (secret) que se utilizará para firmar las solicitudes y verificar su autenticidad.</p>
                
                <p><strong>Formato de la solicitud:</strong></p>
                <ul>
                    <li>Método: POST</li>
                    <li>Headers:
                        <ul>
                            <li>Content-Type: application/json</li>
                            <li>X-Webhook-Signature: [firma HMAC SHA256]</li>
                        </ul>
                    </li>
                    <li>Cuerpo: JSON con los detalles del evento</li>
                </ul>
                
                <p><strong>Recomendaciones:</strong></p>
                <ul>
                    <li>Asegúrese de que la URL de destino esté disponible y responda con un código 2xx en menos de 10 segundos.</li>
                    <li>Verifique la firma en el encabezado X-Webhook-Signature para confirmar la autenticidad de la solicitud.</li>
                    <li>Implemente el procesamiento asíncrono para manejar las notificaciones de manera eficiente.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    // Validar que al menos un evento esté seleccionado
    document.querySelector('form').addEventListener('submit', function(e) {
        const checkboxes = document.querySelectorAll('input[name="events[]"]:checked');
        
        if (checkboxes.length === 0) {
            e.preventDefault();
            alert('Debe seleccionar al menos un evento.');
        }
    });
</script>
<?= $this->endSection() ?>