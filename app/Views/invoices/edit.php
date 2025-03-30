<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Factura<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Editar Factura</h1>
    </div>
</div>

<?php if (session()->has('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= session('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form action="<?= site_url('invoices/update/' . $invoice['uuid']) ?>" method="post">
            <?= csrf_field() ?>
            
            <div class="mb-3">
                <label for="client_id" class="form-label">Cliente *</label>
                <select name="client_id" id="client_id" class="form-select" required>
                    <option value="">Seleccione un cliente</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>" <?= old('client_id', $invoice['client_id']) == $client['id'] ? 'selected' : '' ?>>
                            <?= esc($client['business_name']) ?> (<?= esc($client['document_number']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (session('validation') && session('validation')->hasError('client_id')): ?>
                    <div class="invalid-feedback d-block">
                        <?= session('validation')->getError('client_id') ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mb-3">
                <label for="invoice_number" class="form-label">Número de Factura *</label>
                <input type="text" class="form-control <?= session('validation') && session('validation')->hasError('invoice_number') ? 'is-invalid' : '' ?>" 
                       id="invoice_number" name="invoice_number" 
                       value="<?= old('invoice_number', $invoice['invoice_number']) ?>" required maxlength="50">
                <?php if (session('validation') && session('validation')->hasError('invoice_number')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('invoice_number') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="concept" class="form-label">Concepto *</label>
                <input type="text" class="form-control <?= session('validation') && session('validation')->hasError('concept') ? 'is-invalid' : '' ?>" 
                       id="concept" name="concept" 
                       value="<?= old('concept', $invoice['concept']) ?>" required maxlength="255">
                <?php if (session('validation') && session('validation')->hasError('concept')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('concept') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="amount" class="form-label">Importe *</label>
                <input type="number" class="form-control <?= session('validation') && session('validation')->hasError('amount') ? 'is-invalid' : '' ?>" 
                       id="amount" name="amount" step="0.01" 
                       value="<?= old('amount', $invoice['amount']) ?>" required>
                <?php if (session('validation') && session('validation')->hasError('amount')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('amount') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="currency" class="form-label">Moneda *</label>
                <select name="currency" id="currency" class="form-select <?= session('validation') && session('validation')->hasError('currency') ? 'is-invalid' : '' ?>" required>
                    <option value="PEN" <?= old('currency', $invoice['currency']) === 'PEN' ? 'selected' : '' ?>>PEN - Soles</option>
                    <option value="USD" <?= old('currency', $invoice['currency']) === 'USD' ? 'selected' : '' ?>>USD - Dólares</option>
                </select>
                <?php if (session('validation') && session('validation')->hasError('currency')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('currency') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Estado *</label>
                <select name="status" id="status" class="form-select <?= session('validation') && session('validation')->hasError('status') ? 'is-invalid' : '' ?>" required>
                    <option value="pending" <?= old('status', $invoice['status']) === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                    <?php if ($invoice['status'] === 'paid'): ?>
                        <option value="paid" selected>Pagada</option>
                    <?php endif; ?>
                    <option value="cancelled" <?= old('status', $invoice['status']) === 'cancelled' ? 'selected' : '' ?>>Anulada</option>
                    <option value="rejected" <?= old('status', $invoice['status']) === 'rejected' ? 'selected' : '' ?>>Rechazada</option>
                    <option value="expired" <?= old('status', $invoice['status']) === 'expired' ? 'selected' : '' ?>>Vencida</option>
                </select>
                <div class="form-text">
                    Nota: El estado "Pagada" solo se puede establecer automáticamente al registrar pagos que cubran el monto total.
                </div>
                <?php if (session('validation') && session('validation')->hasError('status')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('status') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="due_date" class="form-label" id="due_date_label">Fecha de Vencimiento *</label>
                <input type="date" class="form-control <?= session('validation') && session('validation')->hasError('due_date') ? 'is-invalid' : '' ?>" 
                       id="due_date" name="due_date" 
                       value="<?= old('due_date', $invoice['due_date']) ?>" required>
                <?php if (session('validation') && session('validation')->hasError('due_date')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('due_date') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="num_instalments" class="form-label">Número de Cuotas *</label>
                <select class="form-select <?= session('validation') && session('validation')->hasError('num_instalments') ? 'is-invalid' : '' ?>" 
                        id="num_instalments" name="num_instalments" required>
                    <?php 
                    // Obtener el número actual de cuotas
                    $instalmentModel = new \App\Models\InstalmentModel();
                    $currentInstalments = $instalmentModel->where('invoice_id', $invoice['id'])->countAllResults();
                    $currentInstalments = $currentInstalments > 0 ? $currentInstalments : 1;
                    
                    for ($i = 1; $i <= 12; $i++): 
                    ?>
                        <option value="<?= $i ?>" <?= old('num_instalments', $currentInstalments) == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
                <?php if (session('validation') && session('validation')->hasError('num_instalments')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('num_instalments') ?>
                    </div>
                <?php endif; ?>
                <?php if ($currentInstalments > 0): ?>
                    <div class="form-text text-warning">
                        <i class="bi bi-exclamation-triangle"></i> Cambiar el número de cuotas recreará todas las cuotas existentes.
                        <?php if ($currentInstalments > 1): ?>
                            Actualmente hay <?= $currentInstalments ?> cuotas.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="instalment_interval_container" class="mb-3" style="display: none;">
                <label for="instalment_interval" class="form-label">Intervalo entre cuotas (días) *</label>
                <input type="number" class="form-control <?= session('validation') && session('validation')->hasError('instalment_interval') ? 'is-invalid' : '' ?>" 
                       id="instalment_interval" name="instalment_interval" 
                       value="<?= old('instalment_interval', 30) ?>" min="1" max="90">
                <?php if (session('validation') && session('validation')->hasError('instalment_interval')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('instalment_interval') ?>
                    </div>
                <?php endif; ?>
                <div id="instalment_preview" class="alert alert-info mt-2" style="display: none;">
                    <p class="mb-0">
                        <strong>Monto por cuota:</strong> <span id="instalment_amount">Calculando...</span>
                        <br>
                        <small class="text-muted">El monto de la última cuota puede variar ligeramente para ajustar el total.</small>
                    </p>
                </div>
            </div>

            <div class="mb-3">
                <label for="external_id" class="form-label">ID Externo</label>
                <input type="text" class="form-control <?= session('validation') && session('validation')->hasError('external_id') ? 'is-invalid' : '' ?>" 
                       id="external_id" name="external_id" 
                       value="<?= old('external_id', $invoice['external_id']) ?>" maxlength="36">
                <?php if (session('validation') && session('validation')->hasError('external_id')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('external_id') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="notes" class="form-label">Notas</label>
                <textarea class="form-control <?= session('validation') && session('validation')->hasError('notes') ? 'is-invalid' : '' ?>" 
                          id="notes" name="notes" rows="3"><?= old('notes', $invoice['notes']) ?></textarea>
                <?php if (session('validation') && session('validation')->hasError('notes')): ?>
                    <div class="invalid-feedback">
                        <?= session('validation')->getError('notes') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= site_url('invoices') ?>" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejo de cuotas
    const numInstalmentsSelect = document.getElementById('num_instalments');
    const instalmentIntervalContainer = document.getElementById('instalment_interval_container');
    const instalmentPreview = document.getElementById('instalment_preview');
    const dueDateLabel = document.getElementById('due_date_label');
    const amountInput = document.getElementById('amount');
    const instalmentAmountSpan = document.getElementById('instalment_amount');
    const currencySelect = document.getElementById('currency');
    
    function toggleInstalmentFields() {
        const numInstalments = parseInt(numInstalmentsSelect.value);
        
        if (numInstalments > 1) {
            instalmentIntervalContainer.style.display = 'block';
            instalmentPreview.style.display = 'block';
            dueDateLabel.textContent = 'Fecha de Vencimiento de la Primera Cuota *';
        } else {
            instalmentIntervalContainer.style.display = 'none';
            instalmentPreview.style.display = 'none';
            dueDateLabel.textContent = 'Fecha de Vencimiento *';
        }
        
        updateInstalmentAmount();
    }
    
    function updateInstalmentAmount() {
        const totalAmount = parseFloat(amountInput.value) || 0;
        const numInstalments = parseInt(numInstalmentsSelect.value) || 1;
        const symbol = currencySelect.value === 'PEN' ? 'S/ ' : '$ ';
        
        if (totalAmount > 0 && numInstalments > 0) {
            const instalmentAmount = (totalAmount / numInstalments).toFixed(2);
            instalmentAmountSpan.textContent = symbol + instalmentAmount;
        } else {
            instalmentAmountSpan.textContent = symbol + '0.00';
        }
    }
    
    numInstalmentsSelect.addEventListener('change', toggleInstalmentFields);
    amountInput.addEventListener('input', updateInstalmentAmount);
    currencySelect.addEventListener('change', updateInstalmentAmount);
    
    // Inicializar campos de cuotas
    toggleInstalmentFields();
});
</script>
<?= $this->endSection() ?>