<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The proxies in front of the app, whose X-Forwarded-For and X-Forwarded-Proto
    | headers are believed. Requests that come from anywhere else have those
    | headers ignored, so visitors can't pretend to be another IP address.
    |
    | Defaults to Cloudflare's published ranges (https://www.cloudflare.com/ips).
    | Set TRUSTED_PROXIES to a comma separated list to use other proxies instead.
    |
    | Only the visitor's IP address and the scheme are taken from these headers,
    | never the host or port; see trustProxies() in bootstrap/app.php.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: [
        // IPv4
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',

        // IPv6
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ],

];
