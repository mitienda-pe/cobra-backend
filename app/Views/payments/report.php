<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Reporte de Pagos<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col">
        <h1>Reporte de Pagos</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= site_url('payments') ?>">Pagos</a></li>
                <li class="breadcrumb-item active">Reporte</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= site_url('payments/report') ?>" class="row g-3">
            <div class="col-md-4">
                <label for="date_start" class="form-label">Fecha Desde</label>
                <input type="date" class="form-control" id="date_start" name="date_start" value="<?= $date_start ?>">
            </div>
            <div class="col-md-4">
                <label for="date_end" class="form-label">Fecha Hasta</label>
                <input type="date" class="form-control" id="date_end" name="date_end" value="<?= $date_end ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="<?= site_url('payments/report') ?>" class="btn btn-outline-secondary ms-2">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($payments)): ?>
    <div class="alert alert-info">
        No se encontraron pagos con los filtros aplicados.
    </div>
<?php else: ?>
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    Resumen
                </div>
                <div class="card-body">
                    <h2 class="text-primary text-center mb-4">$<?= number_format($totalAmount, 2) ?></h2>
                    <p class="text-center mb-4">Total recaudado</p>
                    
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total de pagos
                            <span class="badge bg-primary rounded-pill"><?= count($payments) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Período
                            <span class="text-muted"><?= date('d/m/Y', strtotime($date_start)) ?> - <?= date('d/m/Y', strtotime($date_end)) ?></span>
                        </li>
                    </ul>
                </div>
                <div class="card-footer">
                    <a href="#" class="btn btn-outline-primary btn-sm" onclick="exportData()">
                        <i class="bi bi-download"></i> Exportar Datos
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    Por Método de Pago
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Método</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paymentsByMethod as $method => $data): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            $methodName = '';
                                            switch ($method) {
                                                case 'cash':
                                                    $methodName = 'Efectivo';
                                                    break;
                                                case 'transfer':
                                                    $methodName = 'Transferencia';
                                                    break;
                                                case 'check':
                                                    $methodName = 'Cheque';
                                                    break;
                                                case 'credit_card':
                                                    $methodName = 'Tarjeta de Crédito';
                                                    break;
                                                case 'debit_card':
                                                    $methodName = 'Tarjeta de Débito';
                                                    break;
                                                case 'qr_code':
                                                    $methodName = 'Código QR';
                                                    break;
                                                default:
                                                    $methodName = $method;
                                            }
                                            echo esc($methodName);
                                            ?>
                                        </td>
                                        <td class="text-end"><?= $data['count'] ?></td>
                                        <td class="text-end">$<?= number_format($data['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end"><?= count($payments) ?></th>
                                    <th class="text-end">$<?= number_format($totalAmount, 2) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    Por Cobrador
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Cobrador</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paymentsByCollector as $collector => $data): ?>
                                    <tr>
                                        <td><?= esc($collector) ?></td>
                                        <td class="text-end"><?= $data['count'] ?></td>
                                        <td class="text-end">$<?= number_format($data['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end"><?= count($payments) ?></th>
                                    <th class="text-end">$<?= number_format($totalAmount, 2) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            Detalle de Pagos
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="payments-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Factura</th>
                            <th>Monto</th>
                            <th>Método</th>
                            <th>Cobrador</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($payment['payment_date'])) ?></td>
                                <td><?= esc($payment['business_name'] ?? 'N/A') ?></td>
                                <td><?= esc($payment['invoice_number'] ?? 'N/A') ?></td>
                                <td>$<?= number_format($payment['amount'], 2) ?></td>
                                <td><?= esc($payment['payment_method']) ?></td>
                                <td><?= esc($payment['collector_name'] ?? 'N/A') ?></td>
                                <td>
                                    <a href="<?= site_url('payments/view/' . $payment['id']) ?>" class="btn btn-sm btn-info">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    // Función para exportar datos a CSV
    function exportData() {
        // Crear un objeto de tabla desde la tabla HTML
        const table = document.getElementById('payments-table');
        
        // Encabezados
        let csvContent = "data:text/csv;charset=utf-8,";
        
        // Obtener todas las filas
        const rows = table.querySelectorAll('tr');
        
        rows.forEach(row => {
            // Obtener todas las celdas en la fila
            const cells = row.querySelectorAll('th, td');
            const rowData = [];
            
            cells.forEach(cell => {
                // Obtener el texto de la celda y eliminar acciones
                let text = cell.innerText;
                
                // Si es la última columna (acciones), omitir
                if(cell === cells[cells.length - 1] && !cell.textContent.includes('Acciones')) {
                    return;
                }
                
                // Limpiar y formatear el texto
                text = text.replace(/"/g, '""'); // Escapar comillas dobles
                text = `"${text}"`; // Encerrar en comillas
                
                rowData.push(text);
            });
            
            // Añadir la fila al CSV
            csvContent += rowData.join(',') + '\r\n';
        });
        
        // Crear un enlace para descargar
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `reporte_pagos_${formatDate(new Date())}.csv`);
        document.body.appendChild(link);
        
        // Descargar el archivo
        link.click();
        
        // Limpiar
        document.body.removeChild(link);
    }
    
    // Función para formatear la fecha en YYYY-MM-DD
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        
        return `${year}-${month}-${day}`;
    }
</script>
<?= $this->endSection() ?>