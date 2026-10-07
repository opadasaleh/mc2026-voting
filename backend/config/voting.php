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

    'rate_limits' => [
        'venue_ip_per_minute' => (int) env('RATE_LIMIT_VENUE_IP', 5000),
        'offsite_ip_per_minute' => (int) env('RATE_LIMIT_OFFSITE_IP', 30),
    ],

];
