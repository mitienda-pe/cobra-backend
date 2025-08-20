-- Actualizar solo los 2 pagos reales de producción
-- ID 23: 2 soles (real)
UPDATE payments 
SET ligo_environment = 'prod' 
WHERE id = 23 AND payment_method = 'ligo_qr';

-- ID 22: 3 soles (real)  
UPDATE payments 
SET ligo_environment = 'prod' 
WHERE id = 22 AND payment_method = 'ligo_qr';

-- Marcar el resto como development/test
UPDATE payments 
SET ligo_environment = 'dev' 
WHERE payment_method = 'ligo_qr' 
  AND id NOT IN (22, 23)
  AND ligo_environment IS NULL;

-- Verificar los cambios
SELECT id, amount, external_id, ligo_environment, payment_date 
FROM payments 
WHERE payment_method = 'ligo_qr' 
ORDER BY created_at DESC;