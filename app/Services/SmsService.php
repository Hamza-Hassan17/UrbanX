<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Vonage\Client as VonageClient;
use Vonage\Client\Credentials\Basic;
use Vonage\SMS\Message\SMS;

class SmsService
{
    protected ?VonageClient $client = null;

    public function __construct()
    {
        $apiKey = config('services.vonage.api_key');
        $apiSecret = config('services.vonage.api_secret');

        if ($apiKey && $apiSecret) {
            $this->client = new VonageClient(new Basic($apiKey, $apiSecret));
        }
    }

    /**
     * Send a plain-text SMS to the given phone number (E.164 format, e.g. +923001234567).
     * Returns true on success, false on failure (failures are logged, never thrown,
     * so a down SMS provider never breaks the calling request).
     */
    public function send(string $to, string $message): bool
    {
        $from = config('services.vonage.from');

        if (!$this->client || !$from) {
            Log::warning('SMS not sent: Vonage is not configured.', ['to' => $to]);
            return false;
        }

        try {
            $response = $this->client->sms()->send(new SMS($to, $from, $message));
            $sent = $response->current();

            if ($sent->getStatus() !== 0) {
                Log::error('Vonage rejected SMS', [
                    'to' => $to,
                    'status' => $sent->getStatus(),
                    'message_id' => $sent->getMessageId(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $th) {
            Log::error('Failed to send SMS via Vonage', [
                'to' => $to,
                'error' => $th->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate a random numeric OTP and send it to the given phone number.
     * Returns the generated OTP so the caller can store/compare it — generation
     * always succeeds; only delivery can fail (logged, not thrown).
     */
    public function sendOtp(string $to, int $length = 4): string
    {
        $otp = (string) random_int(
            (int) str_pad('1', $length, '0'),
            (int) str_pad('9', $length, '9')
        );

        $this->send($to, "Your " . config('app.name') . " verification code is {$otp}. It expires in 10 minutes.");

        return $otp;
    }
}
