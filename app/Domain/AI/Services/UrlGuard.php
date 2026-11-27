<?php

namespace App\Domain\AI\Services;

use InvalidArgumentException;

/**
 * SSRF protection for admin-configurable provider URLs: https only for cloud drivers;
 * local drivers (ollama, openai_compatible) may use http, but never link-local / metadata ranges.
 */
class UrlGuard
{
    public static function assertAllowed(string $url, string $driver): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Neplatná URL adresa.');
        }
        $local = in_array($driver, ['ollama', 'openai_compatible'], true);
        if (! $local && $scheme !== 'https') {
            throw new InvalidArgumentException('Cloudoví poskytovatelé vyžadují https.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('URL nesmí obsahovat přihlašovací údaje.');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        foreach ($ips as $ip) {
            // Cloud metadata & link-local are never allowed.
            if (str_starts_with($ip, '169.254.') || $ip === '0.0.0.0' || str_starts_with($ip, 'fe80:')) {
                throw new InvalidArgumentException('Tato adresa není povolená.');
            }
            if (! $local && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Cloudový poskytovatel nesmí mířit do privátní sítě.');
            }
        }
    }
}
