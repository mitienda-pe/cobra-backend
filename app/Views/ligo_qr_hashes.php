<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hashes QR Ligo</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 6px 10px; }
        th { background: #f7f7f7; }
        tr:nth-child(even) { background: #f2f2f2; }
        .hash { font-family: monospace; }
    </style>
</head>
<body>
    <h2>Hashes QR generados por Ligo</h2>
    <table>
        <thead>
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
                <tr><td colspan="8">No hay hashes generados.</td></tr>
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
</body>
</html>
