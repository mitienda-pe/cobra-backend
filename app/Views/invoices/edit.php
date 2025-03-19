<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Cuenta por Cobrar<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Editar Cuenta por Cobrar</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= site_url('invoices') ?>">Cuentas por Cobrar</a></li>
                <li class="breadcrumb-item active">Editar Cuenta</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form action="<?= site_url('invoices/edit/' . $invoice['id']) ?>" method="post">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Cliente</label>
                        <input type="text" class="form-control" value="<?= esc($client['business_name']) ?> (<?= esc($client['document_number']) ?>)" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label for="invoice_number" class="form-label">Número de Factura *</label>
                        <input type="text" class="form-control" id="invoice_number" name="invoice_number" 
                               value="<?= old('invoice_number', $invoice['invoice_number']) ?>" required maxlength="50">
                    </div>
                    
                    <div class="mb-3">
                        <label for="concept" class="form-label">Concepto *</label>
                        <input type="text" class="form-control" id="concept" name="concept" 
                               value="<?= old('concept', $invoice['concept']) ?>" required maxlength="255">
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Monto *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   value="<?= old('amount', $invoice['amount']) ?>" required step="0.01" min="0.01">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="due_date" class="form-label">Fecha de Vencimiento *</label>
                        <input type="date" class="form-control" id="due_date" name="due_date" 
                               value="<?= old('due_date', date('Y-m-d', strtotime($invoice['due_date']))) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Estado *</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="pending" <?= old('status', $invoice['status']) === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="cancelled" <?= old('status', $invoice['status']) === 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
                            <option value="rejected" <?= old('status', $invoice['status']) === 'rejected' ? 'selected' : '' ?>>Rechazada</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="external_id" class="form-label">ID Externo (Opcional)</label>
                        <input type="text" class="form-control" id="external_id" name="external_id" 
                               value="<?= old('external_id', $invoice['external_id']) ?>" maxlength="36">
                        <div class="form-text">ID de referencia en sistema externo.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notas (Opcional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= old('notes', $invoice['notes']) ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?= site_url('invoices') ?>" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
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
                <p>No es posible cambiar el cliente asociado a una cuenta por cobrar.</p>
                <p>Si necesita cambiar el cliente, debe eliminar esta cuenta y crear una nueva.</p>
                <p>No es posible establecer el estado a "Pagada" manualmente. Este estado se actualiza automáticamente cuando se registra un pago completo.</p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>