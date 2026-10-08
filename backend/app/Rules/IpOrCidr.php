<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A single IPv4/IPv6 address or a CIDR range, e.g. 203.0.113.0/24 or 2001:db8:1::/48.
 */
class IpOrCidr implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail(sprintf('"%s" is not a valid IP address or CIDR range (e.g. 203.0.113.0/24).', is_scalar($value) ? $value : ''));
        }
    }

    public static function isValid(string $value): bool
    {
        [$ip, $bits] = array_pad(explode('/', trim($value), 2), 2, null);

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return $bits === null || (ctype_digit($bits) && (int) $bits <= (str_contains($ip, ':') ? 128 : 32));
    }
}
