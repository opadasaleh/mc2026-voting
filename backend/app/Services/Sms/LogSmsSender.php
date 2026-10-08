<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Development / demo driver: writes each message (including OTP codes) to
 * storage/logs/sms.log instead of sending it. Never use with real visitors.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::channel('sms')->info("SMS to {$to}: {$message}");
    }
}
