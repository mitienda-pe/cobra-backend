-- Corrección para la factura ccInrtI5
-- Marcar las cuotas 5 y 6 como pagadas ya que la factura está 100% pagada

UPDATE instalments 
SET status = 'paid', updated_at = datetime('now')
WHERE id IN (67, 68) 
AND invoice_id = (SELECT id FROM invoices WHERE uuid = 'ccInrtI5');

-- Verificar el resultado
SELECT 
    i.number as cuota,
    i.status as estado_cuota,
    i.amount as monto_cuota
FROM instalments i 
JOIN invoices inv ON inv.id = i.invoice_id 
WHERE inv.uuid = 'ccInrtI5' 
ORDER BY i.number;