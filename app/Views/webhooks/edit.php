<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Webhook<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Editar Webhook</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= site_url('webhooks') ?>">Webhooks</a></li>
                <li class="breadcrumb-item active">Editar Webhook</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form action="<?= site_url('webhooks/edit/' . $webhook['id']) ?>" method="post">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?= old('name', $webhook['name']) ?>" required minlength="3" maxlength="100">
                        <div class="form-text">Un nombre descriptivo para este webhook (ej. "Notificación ERP").</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="url" class="form-label">URL *</label>
                        <input type="url" class="form-control" id="url" name="url" 
                               value="<?= old('url', $webhook['url']) ?>" required>
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
                                               <?= in_array($value, $selectedEvents) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="event_<?= $value ?>">
                                            <?= $label ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="secret" class="form-label">Clave Secreta</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="secret" value="<?= esc($webhook['secret']) ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleSecretVisibility()">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            Esta clave se utiliza para firmar las solicitudes. 
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="regenerate_secret" name="regenerate_secret" value="1">
                                <label class="form-check-label" for="regenerate_secret">
                                    Regenerar clave secreta
                                </label>
                                <div class="form-text text-danger">¡Atención! Esto requerirá actualizar la clave en el sistema receptor.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                               <?= old('is_active', $webhook['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Activo</label>
                        <div class="form-text">Desmarque para desactivar temporalmente este webhook sin eliminarlo.</div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?= site_url('webhooks') ?>" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header">
                Información
            </div>
            <div class="card-body">
                <p>Los webhooks permiten notificar a sistemas externos cuando ocurren eventos en su aplicación.</p>
                
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
                </ul>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                Acciones Rápidas
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?= site_url('webhooks/logs/' . $webhook['id']) ?>" class="btn btn-info">
                        Ver Logs
                    </a>
                    <a href="<?= site_url('webhooks/test/' . $webhook['id']) ?>" class="btn btn-warning">
                        Enviar Evento de Prueba
                    </a>
                </div>
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
    
    // Función para mostrar/ocultar la clave secreta
    function toggleSecretVisibility() {
        const secretInput = document.getElementById('secret');
        const secretButton = secretInput.nextElementSibling.firstElementChild;
        
        if (secretInput.type === 'password') {
            secretInput.type = 'text';
            secretButton.className = 'bi bi-eye-slash';
        } else {
            secretInput.type = 'password';
            secretButton.className = 'bi bi-eye';
        }
    }
    
    // Inicializar como password
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('secret').type = 'password';
    });
</script>
<?= $this->endSection() ?>