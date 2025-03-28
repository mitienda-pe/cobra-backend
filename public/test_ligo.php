<?php

// Cargar el entorno de CodeIgniter
require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require realpath($paths->systemDirectory . '/bootstrap.php');

// Inicializar el entorno
$app = Config\Services::codeigniter();
$app->initialize();

// Cargar modelos necesarios
$invoiceModel = new \App\Models\InvoiceModel();
$organizationModel = new \App\Models\OrganizationModel();

// ID de factura para probar
$invoiceId = isset($_GET['invoice_id']) ? $_GET['invoice_id'] : 5;

// Obtener detalles de la factura
$invoice = $invoiceModel->find($invoiceId);

if (!$invoice) {
    die("Factura no encontrada");
}

// Obtener organización
$organization = $organizationModel->find($invoice['organization_id']);

if (!$organization) {
    die("Organización no encontrada");
}

// Verificar si Ligo está habilitado
if (!isset($organization['ligo_enabled']) || !$organization['ligo_enabled']) {
    die("Ligo no está habilitado para esta organización");
}

// Verificar credenciales
if (empty($organization['ligo_api_key']) || empty($organization['ligo_api_secret'])) {
    die("Credenciales de Ligo no configuradas. API Key: " . 
        (empty($organization['ligo_api_key']) ? "No configurada" : "Configurada") . 
        ", API Secret: " . 
        (empty($organization['ligo_api_secret']) ? "No configurada" : "Configurada"));
}

// Preparar datos para la orden
$orderData = [
    'amount' => $invoice['amount'],
    'currency' => $invoice['currency'] ?? 'PEN',
    'orderId' => $invoice['id'],
    'description' => "Pago factura #{$invoice['invoice_number']}"
];

echo "<h2>Prueba de integración con Ligo</h2>";
echo "<p>Factura ID: {$invoice['id']}, Número: {$invoice['invoice_number']}</p>";
echo "<p>Organización ID: {$organization['id']}, Nombre: {$organization['name']}</p>";
echo "<p>Credenciales de Ligo: API Key: " . substr($organization['ligo_api_key'], 0, 5) . "..., API Secret: " . substr($organization['ligo_api_secret'], 0, 5) . "...</p>";
echo "<p>Datos para la orden: " . json_encode($orderData) . "</p>";

// Función para crear orden en Ligo
function createLigoOrder($data, $organization) {
    echo "<h3>Llamada a la API de Ligo</h3>";
    
    $curl = curl_init();
    
    $headers = [
        'Authorization: Bearer ' . $organization['ligo_api_key'],
        'Content-Type: application/json'
    ];
    
    echo "<p>Headers: " . json_encode($headers) . "</p>";
    echo "<p>Datos: " . json_encode($data) . "</p>";
    
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.ligo.pe/v1/orders',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_VERBOSE => true
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $info = curl_getinfo($curl);
    
    echo "<p>Info de cURL: " . json_encode($info) . "</p>";
    
    curl_close($curl);
    
    if ($err) {
        echo "<p style='color: red;'>Error de cURL: " . $err . "</p>";
        return (object)['error' => 'Failed to connect to Ligo API: ' . $err];
    }
    
    // Mostrar la respuesta cruda exactamente como se recibió
    echo "<h4>Respuesta cruda (sin procesar):</h4>";
    echo "<pre style='background-color: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;'>";
    echo htmlspecialchars($response);
    echo "</pre>";
    
    // Verificar si la respuesta es HTML
    if (strpos($response, '<!DOCTYPE html>') !== false || strpos($response, '<html') !== false) {
        echo "<p style='color: red;'>La respuesta parece ser HTML, no JSON. Esto podría indicar un error de autenticación o una redirección.</p>";
    }
    
    // Intentar decodificar como JSON
    $decoded = json_decode($response);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<p style='color: red;'>Error decodificando JSON: " . json_last_error_msg() . "</p>";
        return (object)['error' => 'Invalid JSON response: ' . json_last_error_msg()];
    }
    
    if (isset($decoded->error) || isset($decoded->message) || $info['http_code'] >= 400) {
        $errorMsg = isset($decoded->error) ? $decoded->error : 
                   (isset($decoded->message) ? $decoded->message : 'HTTP Error: ' . $info['http_code']);
        echo "<p style='color: red;'>Error en la respuesta de la API: " . $errorMsg . "</p>";
        return (object)['error' => $errorMsg];
    }
    
    echo "<p style='color: green;'>Respuesta exitosa: " . json_encode($decoded) . "</p>";
    
    return $decoded;
}

// Llamar a la API de Ligo
$response = createLigoOrder($orderData, $organization);

// Mostrar resultados
if (!isset($response->error)) {
    echo "<h3>QR generado exitosamente</h3>";
    if (isset($response->qr_image_url)) {
        echo "<p>URL de la imagen QR: {$response->qr_image_url}</p>";
        echo "<img src='{$response->qr_image_url}' alt='QR Code' style='max-width: 250px;'>";
    } else {
        echo "<p>No se recibió URL de imagen QR en la respuesta</p>";
    }
} else {
    echo "<h3>Error al generar QR</h3>";
    echo "<p>{$response->error}</p>";
}
