<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Cliente<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Editar Cliente</h3>
                    <div>
                        <a href="<?= site_url('clients/' . $client['uuid']) ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form action="<?= site_url('clients/' . $client['uuid']) ?>" method="post">
                    <?= csrf_field() ?>
                    
                    <?php if ($auth->hasRole('superadmin')): ?>
                    <!-- Organization information -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Información de Organización</h6>
                                    <?php if ($auth->organizationId()): ?>
                                        <?php 
                                            $orgModel = new \App\Models\OrganizationModel();
                                            $org = $orgModel->find($auth->organizationId());
                                            $orgName = $org ? $org['name'] : 'Desconocida';
                                        ?>
                                        <div class="alert alert-info mb-0">
                                            <i class="bi bi-building"></i> Cliente de: <strong><?= esc($orgName) ?></strong>
                                        </div>
                                        <input type="hidden" name="organization_id" value="<?= $auth->organizationId() ?>">
                                    <?php else: ?>
                                        <div class="form-group">
                                            <label for="organization_id" class="form-label">Organización *</label>
                                            <select class="form-select <?= session('errors.organization_id') ? 'is-invalid' : '' ?>" id="organization_id" name="organization_id" required>
                                                <option value="">Seleccionar organización</option>
                                                <?php foreach ($organizations as $org): ?>
                                                    <option value="<?= $org['id'] ?>" <?= old('organization_id', $client['organization_id']) == $org['id'] ? 'selected' : '' ?>>
                                                        <?= esc($org['name']) ?> <?= ($org['status'] == 'inactive') ? '(Inactiva)' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (session('errors.organization_id')): ?>
                                                <div class="invalid-feedback"><?= session('errors.organization_id') ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Client Information -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="business_name" class="form-label">Nombre Comercial *</label>
                            <input type="text" class="form-control <?= session('errors.business_name') ? 'is-invalid' : '' ?>" id="business_name" name="business_name" value="<?= old('business_name', $client['business_name']) ?>" required>
                            <?php if (session('errors.business_name')): ?>
                                <div class="invalid-feedback"><?= session('errors.business_name') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="legal_name" class="form-label">Razón Social *</label>
                            <input type="text" class="form-control <?= session('errors.legal_name') ? 'is-invalid' : '' ?>" id="legal_name" name="legal_name" value="<?= old('legal_name', $client['legal_name']) ?>" required>
                            <?php if (session('errors.legal_name')): ?>
                                <div class="invalid-feedback"><?= session('errors.legal_name') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="document_number" class="form-label">RUC/Documento *</label>
                            <input type="text" class="form-control <?= session('errors.document_number') ? 'is-invalid' : '' ?>" id="document_number" name="document_number" value="<?= old('document_number', $client['document_number']) ?>" required>
                            <?php if (session('errors.document_number')): ?>
                                <div class="invalid-feedback"><?= session('errors.document_number') ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="external_id" class="form-label">ID Externo (para integración)</label>
                            <input type="text" class="form-control" id="external_id" name="external_id" value="<?= old('external_id', $client['external_id']) ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Estado *</label>
                            <select class="form-select <?= session('errors.status') ? 'is-invalid' : '' ?>" id="status" name="status" required>
                                <option value="active" <?= old('status', $client['status']) == 'active' ? 'selected' : '' ?>>Activo</option>
                                <option value="inactive" <?= old('status', $client['status']) == 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                            <?php if (session('errors.status')): ?>
                                <div class="invalid-feedback"><?= session('errors.status') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">Información de Contacto</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="contact_name" class="form-label">Nombre de Contacto</label>
                            <input type="text" class="form-control <?= session('errors.contact_name') ? 'is-invalid' : '' ?>" id="contact_name" name="contact_name" value="<?= old('contact_name', $client['contact_name']) ?>">
                            <?php if (session('errors.contact_name')): ?>
                                <div class="invalid-feedback"><?= session('errors.contact_name') ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="contact_phone" class="form-label">Teléfono de Contacto</label>
                            <input type="tel" class="form-control <?= session('errors.contact_phone') ? 'is-invalid' : '' ?>" id="contact_phone" name="contact_phone" value="<?= old('contact_phone', $client['contact_phone']) ?>">
                            <?php if (session('errors.contact_phone')): ?>
                                <div class="invalid-feedback"><?= session('errors.contact_phone') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Dirección</label>
                        <textarea class="form-control <?= session('errors.address') ? 'is-invalid' : '' ?>" id="address" name="address" rows="2"><?= old('address', $client['address']) ?></textarea>
                        <?php if (session('errors.address')): ?>
                            <div class="invalid-feedback"><?= session('errors.address') ?></div>
                        <?php endif; ?>
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
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Actualizar Cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>