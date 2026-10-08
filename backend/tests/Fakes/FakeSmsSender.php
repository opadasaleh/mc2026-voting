<?php

namespace Tests\Fakes;

use App\Services\Sms\SmsSender;

/**
 * Captures messages instead of sending them.
 */
class FakeSmsSender implements SmsSender
{
    /** @var list<array{to: string, message: string}> */
    public array $sent = [];

    public function send(string $to, string $message): void
    {
        $this->sent[] = ['to' => $to, 'message' => $message];
    }

    public function lastCodeFor(string $to): ?string
    {
        foreach (array_reverse($this->sent) as $sms) {
            if ($sms['to'] === $to && preg_match('/\b(\d{6})\b/', $sms['message'], $match)) {
                return $match[1];
            }
        }

        return null;
    }
}
