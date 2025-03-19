<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Cliente<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Editar Cliente</h3>
                    <a href="<?= site_url('clients') ?>" class="btn btn-secondary">Volver</a>
                </div>
            </div>
            <div class="card-body">
                <form action="<?= site_url('clients/edit/' . $client['id']) ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="business_name" class="form-label">Nombre Comercial *</label>
                            <input type="text" class="form-control" id="business_name" name="business_name" value="<?= old('business_name', $client['business_name']) ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="legal_name" class="form-label">Razón Social *</label>
                            <input type="text" class="form-control" id="legal_name" name="legal_name" value="<?= old('legal_name', $client['legal_name']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="document_number" class="form-label">RUC/Documento *</label>
                            <input type="text" class="form-control" id="document_number" name="document_number" value="<?= old('document_number', $client['document_number']) ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="external_id" class="form-label">ID Externo (para integración)</label>
                            <input type="text" class="form-control" id="external_id" name="external_id" value="<?= old('external_id', $client['external_id']) ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Estado *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?= old('status', $client['status']) == 'active' ? 'selected' : '' ?>>Activo</option>
                                <option value="inactive" <?= old('status', $client['status']) == 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">Información de Contacto</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="contact_name" class="form-label">Nombre de Contacto</label>
                            <input type="text" class="form-control" id="contact_name" name="contact_name" value="<?= old('contact_name', $client['contact_name']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="contact_phone" class="form-label">Teléfono de Contacto</label>
                            <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?= old('contact_phone', $client['contact_phone']) ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Dirección</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?= old('address', $client['address']) ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="ubigeo" class="form-label">Ubigeo</label>
                            <input type="text" class="form-control" id="ubigeo" name="ubigeo" value="<?= old('ubigeo', $client['ubigeo']) ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="zip_code" class="form-label">Código Postal</label>
                            <input type="text" class="form-control" id="zip_code" name="zip_code" value="<?= old('zip_code', $client['zip_code']) ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="latitude" class="form-label">Latitud</label>
                            <input type="text" class="form-control" id="latitude" name="latitude" value="<?= old('latitude', $client['latitude']) ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="longitude" class="form-label">Longitud</label>
                            <input type="text" class="form-control" id="longitude" name="longitude" value="<?= old('longitude', $client['longitude']) ?>">
                        </div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">Asignación a Carteras</h5>
                    <div class="mb-3">
                        <label class="form-label">Carteras de Cobro</label>
                        <div>
                            <?php foreach ($portfolios as $portfolio): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="portfolio_ids[]" id="portfolio_<?= $portfolio['id'] ?>" value="<?= $portfolio['id'] ?>" 
                                        <?= (old('portfolio_ids') && in_array($portfolio['id'], old('portfolio_ids'))) || 
                                            (!old('portfolio_ids') && in_array($portfolio['id'], $assignedPortfolioIds)) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="portfolio_<?= $portfolio['id'] ?>"><?= $portfolio['name'] ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">Actualizar Cliente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>