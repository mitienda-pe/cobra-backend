<?php

namespace App\Libraries;

class Twilio
{
    private $sid;
    private $token;
    private $fromNumber;
    private $enabled;

    public function __construct()
    {
        // Cargar configuración desde variables de entorno
        $this->sid = getenv('TWILIO_SID');
        $this->token = getenv('TWILIO_TOKEN');
        $this->fromNumber = getenv('TWILIO_FROM_NUMBER');
        $this->enabled = getenv('TWILIO_ENABLED') === 'true';
    }

    /**
     * Envía un mensaje SMS utilizando la API de Twilio
     *
     * @param string $to Número de teléfono de destino (formato E.164, ej: +595981123456)
     * @param string $message Texto del mensaje a enviar
     * @return array Respuesta con éxito/error y detalles
     */
    public function sendSms($to, $message)
    {
        // Si Twilio no está habilitado, simular envío exitoso (útil para desarrollo)
        if (!$this->enabled) {
            log_message('info', "TWILIO DISABLED: Would send SMS to $to: $message");
            return [
                'success' => true,
                'message' => 'SMS simulado (Twilio deshabilitado)',
                'sid' => 'SIMULATED_SID_' . time(),
            ];
        }

        // Validar número de teléfono (formato básico E.164)
        if (!preg_match('/^\+[1-9]\d{1,14}$/', $to)) {
            return [
                'success' => false,
                'message' => 'Número de teléfono inválido. Debe estar en formato E.164 (ej: +595981123456)',
            ];
        }

        // Verificar configuración
        if (empty($this->sid) || empty($this->token) || empty($this->fromNumber)) {
            log_message('error', 'TWILIO ERROR: Missing configuration (SID, Token, or From Number)');
            return [
                'success' => false,
                'message' => 'Error de configuración de Twilio',
            ];
        }

        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json";

            // Preparar datos para la solicitud
            $data = [
                'From' => $this->fromNumber,
                'To' => $to,
                'Body' => $message,
            ];

            // Configurar cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->sid}:{$this->token}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Realizar solicitud
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Procesar respuesta
            $responseData = json_decode($response, true);

            if ($statusCode >= 200 && $statusCode < 300 && isset($responseData['sid'])) {
                log_message('info', "TWILIO: SMS sent successfully to $to, SID: {$responseData['sid']}");
                return [
                    'success' => true,
                    'message' => 'SMS enviado correctamente',
                    'sid' => $responseData['sid'],
                ];
            } else {
                $errorMessage = isset($responseData['message']) ? $responseData['message'] : 'Error desconocido';
                log_message('error', "TWILIO ERROR: Failed to send SMS to $to: $errorMessage");
                return [
                    'success' => false,
                    'message' => "Error al enviar SMS: $errorMessage",
                    'status_code' => $statusCode,
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'TWILIO ERROR: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al conectar con Twilio: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Envía un código OTP por SMS
     *
     * @param string $to Número de teléfono de destino
     * @param string $code Código OTP a enviar
     * @param int $expiresInMinutes Tiempo de expiración en minutos
     * @return array Respuesta con éxito/error y detalles
     */
    public function sendOtpSms($to, $code, $expiresInMinutes = 15)
    {
        $message = "Tu código de verificación es: $code. Válido por $expiresInMinutes minutos. No compartas este código con nadie.";
        return $this->sendSms($to, $message);
    }
}
