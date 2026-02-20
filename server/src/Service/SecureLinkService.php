<?php

namespace App\Service;

/**
 * Secure link generation and verification (WordPress/nginx secure_link compatible).
 * Hash: MD5 of "$expires$path$userIp $secret", base64 URL-safe (no padding).
 */
final class SecureLinkService
{
    private static function getSecret(): string
    {
        $v = getenv('SECURE_LINK_SECRET');
        return $v !== false && $v !== '' ? $v : 'xswtvbbny1k';
    }

    /**
     * Build a secure URL with md5 and expires.
     *
     * @param string $baseUrl Base URL including scheme/host, no trailing slash
     * @param string $path    URI path to protect (e.g. /files/serve/123)
     * @param int    $expire  Expiry Unix timestamp
     * @param string $userIp  Client IP (included in hash when non-empty)
     */
    public static function buildSecureLink(string $baseUrl, string $path, int $expire, string $userIp = ''): string
    {
        $secret = self::getSecret();
        $path2 = urldecode($path);
        $expires = (string) $expire;
        $toHash = $expires . $path2 . $userIp . ' ' . $secret;
        $md5 = md5($toHash, true);
        $md5 = base64_encode($md5);
        $md5 = strtr($md5, '+/', '-_');
        $md5 = str_replace('=', '', $md5);
        $url = rtrim($baseUrl, '/') . $path;
        $sep = strpos($url, '?') !== false ? '&' : '?';
        return $url . $sep . 'md5=' . $md5 . '&expires=' . $expires;
    }

    /**
     * Generate a secure download URL for a file by ID (default 24h expiry).
     */
    public static function generateSecureFileLink(int $fileId, string $baseUrl, ?string $userIp = null): string
    {
        $path = '/files/serve/' . $fileId;
        $expire = (int) strtotime('now + 24 hours');
        $ip = $userIp ?? '';
        return self::buildSecureLink($baseUrl, $path, $expire, $ip);
    }

    /**
     * Verify secure link (md5 + expires). Tries with and without client IP.
     */
    public static function verifySecureLink(string $path, string $providedMd5, string $expires, string $userIp): bool
    {
        $expireTs = (int) $expires;
        if ($expireTs < time()) {
            return false;
        }
        $secret = self::getSecret();
        if (hash_equals(self::computeMd5($path, $secret, $expires, $userIp), $providedMd5)) {
            return true;
        }
        if (hash_equals(self::computeMd5($path, $secret, $expires, ''), $providedMd5)) {
            return true;
        }
        return false;
    }

    /**
     * Compute expected md5 token for (path, secret, expires, userIp).
     */
    public static function computeMd5(string $path, string $secret, string $expires, string $userIp): string
    {
        $path2 = urldecode($path);
        $toHash = $expires . $path2 . $userIp . ' ' . $secret;
        $md5 = md5($toHash, true);
        $md5 = base64_encode($md5);
        $md5 = strtr($md5, '+/', '-_');
        return str_replace('=', '', $md5);
    }

    /**
     * Secret used for HMAC (e.g. upload tokens). Same as secure link secret.
     */
    public static function getHmacSecret(): string
    {
        return self::getSecret();
    }
}
