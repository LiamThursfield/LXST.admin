<?php

namespace App\Services\Cms\Api;

use Carbon\CarbonInterface;

/**
 * What a page view keeps about its visitor. The full IP address is never stored: only a truncated
 * one (the network, not the device) and a hash that changes daily, so unique visitors can be
 * counted per day without anyone being followed from one day to the next.
 */
class VisitorPrivacy
{
    /**
     * Fragments of user agents that belong to crawlers, previews, monitors and scripts
     *
     * @var list<string>
     */
    protected const array BOT_FRAGMENTS = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'facebookexternalhit', 'embedly',
        'preview', 'headless', 'lighthouse', 'pingdom', 'uptime', 'curl', 'wget', 'python-',
        'go-http-client', 'java/', 'okhttp', 'axios', 'node-fetch', 'undici', 'guzzlehttp',
        'httpclient', 'scrapy', 'phantomjs', 'selenium', 'puppeteer', 'playwright',
    ];

    /**
     * Whether the user agent looks automated. A missing one counts, as browsers always send one.
     */
    public function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return true;
        }

        $userAgent = strtolower($userAgent);

        return collect(self::BOT_FRAGMENTS)->contains(fn (string $fragment) => str_contains($userAgent, $fragment));
    }

    /**
     * The IP's network: the last part of an IPv4 address zeroed (203.0.113.42 → 203.0.113.0),
     * or all but the first 48 bits of an IPv6 one (2001:db8:85a3::8a2e:370:7334 → 2001:db8:85a3::)
     */
    public function truncateIp(?string $ip): ?string
    {
        $packed = $ip === null ? false : @inet_pton($ip);

        if ($packed === false) {
            return null;
        }

        $keep = strlen($packed) === 4 ? 3 : 6;
        $truncated = inet_ntop(substr($packed, 0, $keep).str_repeat("\0", strlen($packed) - $keep));

        return $truncated === false ? null : $truncated;
    }

    /**
     * A per-tenant, per-day hash of the visitor, keyed by the app key so it can't be reversed by
     * hashing every IP address
     */
    public function visitorHash(?string $ip, ?string $userAgent, CarbonInterface $day): ?string
    {
        if ($ip === null) {
            return null;
        }

        return hash_hmac(
            'sha256',
            implode('|', [tenant()?->getTenantKey(), $day->toDateString(), $ip, $userAgent]),
            (string) config('app.key'),
        );
    }
}
