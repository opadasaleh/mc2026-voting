<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use RuntimeException;

/**
 * The visitor's UID: a mobile number in E.164 form, and its HMAC blind index.
 */
final class PhoneNumber
{
    private const SMS_CAPABLE = [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE];

    /**
     * "0791234567", "+962 79 123 4567" and "00962791234567" all become "+962791234567".
     * Returns null for anything that is not a valid number able to receive SMS.
     */
    public static function normalize(string $input): ?string
    {
        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse(trim($input), config('voting.phone.default_region'));
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isValidNumber($number) || ! in_array($util->getNumberType($number), self::SMS_CAPABLE, true)) {
            return null;
        }

        return $util->format($number, PhoneNumberFormat::E164);
    }

    /**
     * The blind index stored in visitors.phone_hash: lookups never need the plain number.
     */
    public static function hash(string $e164): string
    {
        $key = config('voting.phone.hash_key');

        if (blank($key)) {
            throw new RuntimeException('PHONE_HASH_KEY is not set.');
        }

        return hash_hmac('sha256', $e164, $key);
    }
}
