<?php

namespace App\Libraries;

use Twilio\Rest\Client;

class TwilioService
{
    protected $client;
    protected $fromNumber;

    public function __construct()
    {
        $accountSid = getenv('TWILIO_ACCOUNT_SID');
        $authToken = getenv('TWILIO_AUTH_TOKEN');
        $this->fromNumber = getenv('TWILIO_FROM_NUMBER');

        $this->client = new Client($accountSid, $authToken);
    }

    public function sendSms($to, $message)
    {
        try {
            $result = $this->client->messages->create($to, [
                'from' => $this->fromNumber,
                'body' => $message
            ]);

            log_message('info', '[TwilioService] SMS sent successfully. SID: ' . $result->sid);
            return true;
        } catch (\Exception $e) {
            log_message('error', '[TwilioService] Error sending SMS: ' . $e->getMessage());
            throw $e;
        }
    }
}
