<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected const ENDPOINT = 'https://api.veevotech.com/v3/sendsms';

    /**
     * Send a plain-text SMS to the given phone number (E.164 format, e.g. +923001234567).
     * Returns true on success, false on failure (failures are logged, never thrown,
     * so a down SMS provider never breaks the calling request).
     */
    public function send(string $to, string $message): bool
    {
        $hash = config('services.veevotech.api_hash');

        if (!$hash) {
            Log::warning('SMS not sent: Veevotech is not configured.', ['to' => $to]);
            return false;
        }

        try {
            $response = Http::asJson()->post(self::ENDPOINT, [
                'hash' => $hash,
                'receivernum' => $to,
                'textmessage' => $message,
                'sendernum' => config('services.veevotech.sender_id', 'Default'),
            ]);

            $body = $response->json();

            if (!$response->successful() || ($body['STATUS'] ?? null) !== 'SUCCESSFUL') {
                Log::error('Veevotech rejected SMS', ['to' => $to, 'response' => $body]);
                return false;
            }

            return true;
        } catch (\Throwable $th) {
            Log::error('Failed to send SMS via Veevotech', [
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
