<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Carteras de Cobro<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Carteras de Cobro</h1>
    <div>
        
        <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
            <a href="<?= site_url('portfolios/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nueva Cartera
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th>Clientes</th>
                        <th>Cobrador</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($portfolios)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No hay carteras registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($portfolios as $portfolio): ?>
                            <tr>
                                <td><?= $portfolio['uuid'] ?></td>
                                <td><?= $portfolio['name'] ?></td>
                                <td>
                                    <?php if ($portfolio['status'] == 'active'): ?>
                                        <span class="badge bg-success">Activa</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $db = \Config\Database::connect();
                                    $count = $db->table('client_portfolio')
                                               ->where('portfolio_uuid', $portfolio['uuid'])
                                               ->countAllResults();
                                    echo $count;
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $user = $db->table('portfolio_user pu')
                                             ->select('u.name')
                                             ->join('users u', 'u.id = pu.user_id')
                                             ->where('pu.portfolio_uuid', $portfolio['uuid'])
                                             ->get()
                                             ->getRowArray();
                                    echo $user ? esc($user['name']) : 'Sin asignar';
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="<?= site_url('portfolios/' . $portfolio['uuid']) ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>
                                        <?php if ($auth->hasAnyRole(['superadmin', 'admin'])): ?>
                                            <a href="<?= site_url('portfolios/' . $portfolio['uuid'] . '/edit') ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i> Editar
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-uuid="<?= $portfolio['uuid'] ?>" data-name="<?= esc($portfolio['name']) ?>">
                                                <i class="bi bi-trash"></i> Eliminar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de confirmación para eliminar -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Eliminar Cartera</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro de eliminar la cartera <span id="delete-name"></span>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="<?= site_url('portfolios/') ?>" class="btn btn-danger" id="delete-btn">Eliminar</a>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#deleteModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var uuid = button.data('uuid');
            var name = button.data('name');
            var modal = $(this);
            modal.find('.modal-title').text('Eliminar Cartera ' + name);
            modal.find('#delete-name').text(name);
            modal.find('#delete-btn').attr('href', '<?= site_url('portfolios/') ?>' + uuid + '/delete');
        });
    });
</script>

<?= $this->endSection() ?>