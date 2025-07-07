<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Hashes QR Ligo<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Hashes QR generados por Ligo</h2>
        <a href="<?= site_url('webhooks/ligo-logs') ?>" class="btn btn-info">
            <i class="bi bi-bell"></i> Ver Notificaciones de Ligo
        </a>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Hash</th>
                        <th>Order ID</th>
                        <th>Invoice ID</th>
                        <th>Monto</th>
                        <th>Moneda</th>
                        <th>Descripción</th>
                        <th>Creado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($hashes)): ?>
                        <tr><td colspan="8" class="text-center">No hay hashes generados.</td></tr>
                    <?php else: foreach ($hashes as $h): ?>
                        <tr>
                            <td><?= esc($h['id']) ?></td>
                            <td class="hash"><?= esc($h['hash']) ?></td>
                            <td><?= esc($h['order_id']) ?></td>
                            <td><?= esc($h['invoice_id']) ?></td>
                            <td><?= esc($h['amount']) ?></td>
                            <td><?= esc($h['currency']) ?></td>
                            <td><?= esc($h['description']) ?></td>
                            <td><?= esc($h['created_at']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
