<?php
/**
 * Herramienta de diagnóstico para probar la integración con Ligo
 * Esta herramienta permite probar las credenciales de Ligo y verificar si la autenticación funciona correctamente
 */

// Inicializar la sesión para mostrar mensajes
session_start();

// Función para mostrar mensajes de error o éxito
function showMessage($type, $message) {
    $_SESSION['message_type'] = $type;
    $_SESSION['message'] = $message;
}

// Función para probar la autenticación de Ligo
function testLigoAuth($username, $password, $companyId) {
    // Datos de autenticación
    $authData = [
        'username' => $username,
        'password' => $password
    ];
    
    // URL de autenticación
    $prefix = 'prod'; // Cambiar a 'dev' para entorno de desarrollo
    $url = 'https://cce-auth-' . $prefix . '.ligocloud.tech/v1/auth/sign-in?companyId=' . $companyId;
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($authData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $info = curl_getinfo($curl);
    
    curl_close($curl);
    
    $result = [
        'success' => false,
        'request' => [
            'url' => $url,
            'data' => $authData
        ],
        'response' => null,
        'error' => null,
        'info' => $info
    ];
    
    if ($err) {
        $result['error'] = $err;
        return $result;
    }
    
    // Verificar si la respuesta es HTML
    if (strpos($response, '<!DOCTYPE html>') !== false || strpos($response, '<html') !== false) {
        $result['error'] = 'La API devolvió HTML en lugar de JSON';
        $result['response'] = $response;
        return $result;
    }
    
    $decoded = json_decode($response);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $result['error'] = 'Error decodificando respuesta: ' . json_last_error_msg();
        $result['response'] = $response;
        return $result;
    }
    
    $result['response'] = $decoded;
    
    // Verificar si hay token en la respuesta
    if (isset($decoded->data) && isset($decoded->data->access_token)) {
        $result['success'] = true;
    }
    
    return $result;
}

// Procesar el formulario si se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $companyId = $_POST['company_id'] ?? '';
    
    if (empty($username) || empty($password) || empty($companyId)) {
        showMessage('error', 'Todos los campos son obligatorios');
    } else {
        $result = testLigoAuth($username, $password, $companyId);
        
        if ($result['success']) {
            showMessage('success', 'Autenticación exitosa! Se obtuvo un token válido.');
            $_SESSION['auth_result'] = $result;
        } else {
            showMessage('error', 'Error en la autenticación. Revisa los detalles abajo.');
            $_SESSION['auth_result'] = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Ligo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .json-key {
            color: #d63384;
        }
        .json-string {
            color: #20c997;
        }
        .json-number {
            color: #fd7e14;
        }
        .json-boolean {
            color: #0d6efd;
        }
        .json-null {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <h1 class="mb-4">Diagnóstico de Integración con Ligo</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message_type'] === 'success' ? 'success' : 'danger' ?> mb-4">
                <?= $_SESSION['message'] ?>
            </div>
            <?php 
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Probar Autenticación de Ligo</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Nombre de Usuario</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="company_id" class="form-label">ID de Empresa</label>
                        <input type="text" class="form-control" id="company_id" name="company_id" required>
                        <div class="form-text">Este es el ID de empresa proporcionado por Ligo (companyId)</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Probar Autenticación</button>
                </form>
            </div>
        </div>
        
        <?php if (isset($_SESSION['auth_result'])): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Resultados de la Prueba</h5>
                </div>
                <div class="card-body">
                    <h6>Detalles de la Solicitud:</h6>
                    <pre><code><?= htmlspecialchars(json_encode($_SESSION['auth_result']['request'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></code></pre>
                    
                    <?php if ($_SESSION['auth_result']['error']): ?>
                        <h6 class="text-danger">Error:</h6>
                        <pre><code><?= htmlspecialchars($_SESSION['auth_result']['error']) ?></code></pre>
                    <?php endif; ?>
                    
                    <h6>Información de la Respuesta HTTP:</h6>
                    <pre><code><?= htmlspecialchars(json_encode($_SESSION['auth_result']['info'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></code></pre>
                    
                    <h6>Respuesta de la API:</h6>
                    <?php if (is_string($_SESSION['auth_result']['response']) && strlen($_SESSION['auth_result']['response']) > 1000): ?>
                        <div class="alert alert-warning">La respuesta es demasiado larga para mostrarla completa. Se muestra un extracto.</div>
                        <pre><code><?= htmlspecialchars(substr($_SESSION['auth_result']['response'], 0, 1000)) ?>...</code></pre>
                    <?php else: ?>
                        <pre><code><?= htmlspecialchars(json_encode($_SESSION['auth_result']['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></code></pre>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['auth_result']['success']): ?>
                        <div class="alert alert-success mt-3">
                            <h6 class="mb-0">Token obtenido correctamente:</h6>
                            <pre class="mb-0 mt-2"><code><?= htmlspecialchars($_SESSION['auth_result']['response']->data->access_token) ?></code></pre>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php unset($_SESSION['auth_result']); ?>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para formatear JSON en el navegador
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('pre code').forEach(function(block) {
                if (block.innerHTML.trim().startsWith('{') || block.innerHTML.trim().startsWith('[')) {
                    try {
                        const json = JSON.parse(block.innerHTML);
                        block.innerHTML = syntaxHighlight(JSON.stringify(json, null, 2));
                    } catch (e) {
                        // Si no es JSON válido, dejarlo como está
                    }
                }
            });
        });
        
        function syntaxHighlight(json) {
            json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                var cls = 'json-number';
                if (/^"/.test(match)) {
                    if (/:$/.test(match)) {
                        cls = 'json-key';
                    } else {
                        cls = 'json-string';
                    }
                } else if (/true|false/.test(match)) {
                    cls = 'json-boolean';
                } else if (/null/.test(match)) {
                    cls = 'json-null';
                }
                return '<span class="' + cls + '">' + match + '</span>';
            });
        }
    </script>
</body>
</html>
