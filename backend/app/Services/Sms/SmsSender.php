<?php

namespace App\Services\Sms;

/**
 * Sends a text message. The real gateway is chosen by CPF before the pilot;
 * add an implementation for it and select it with SMS_DRIVER.
 */
interface SmsSender
{
    /**
     * @param  string  $to  E.164 phone number
     */
    public function send(string $to, string $message): void;
}
