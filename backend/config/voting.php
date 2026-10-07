<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Reverse proxies / load balancers in front of the API, as a comma-separated
    | list of IPs or CIDRs. Only these may set X-Forwarded-For; the header is
    | ignored from anyone else, so a client cannot claim to be on the venue Wi-Fi.
    |
    */

    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Per-IP rate limits (requests per minute)
    |--------------------------------------------------------------------------
    |
    | Every visitor at the venue shares the Wi-Fi's public IP, so venue IPs only
    | get a safety ceiling; visitor limits are keyed on phone and token instead.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Admin panel
    |--------------------------------------------------------------------------
    |
    | An event has at most max_categories_per_event award categories, and each
    | exhibitor competes in exactly one of them.
    |
    | Times are stored in UTC and shown to admins in this time zone. Exhibitor
    | photos go to this filesystem disk (use an S3 disk such as Supabase
    | Storage in production so the app stays stateless).
    |
    */

    'max_categories_per_event' => (int) env('MAX_CATEGORIES_PER_EVENT', 3),

    'display_timezone' => env('ADMIN_TIMEZONE', 'Asia/Amman'),

    'photos_disk' => env('PHOTOS_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Visitors, OTP and SMS
    |--------------------------------------------------------------------------
    |
    | The phone number is the visitor's UID: normalised to E.164 (local numbers
    | are read as default_region) and looked up through an HMAC blind index keyed
    | with PHONE_HASH_KEY. Keep that key secret and backed up: without it,
    | existing visitors can no longer be matched by phone.
    |
    | SMS_DRIVER "log" writes messages to storage/logs/sms.log instead of
    | sending them (development and demos only).
    |
    */

    'phone' => [
        'default_region' => env('PHONE_DEFAULT_REGION', 'JO'),
        'hash_key' => env('PHONE_HASH_KEY'),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    'otp' => [
        'requests_per_phone_per_15_minutes' => (int) env('OTP_REQUESTS_PER_PHONE', 5),
        'verifications_per_phone_per_minute' => (int) env('OTP_VERIFICATIONS_PER_PHONE', 10),
    ],

    'visitor_token_hours' => (int) env('VISITOR_TOKEN_HOURS', 12),

    'rate_limits' => [
        'venue_ip_per_minute' => (int) env('RATE_LIMIT_VENUE_IP', 5000),
        'offsite_ip_per_minute' => (int) env('RATE_LIMIT_OFFSITE_IP', 30),
    ],

];
