<?php

namespace App\Service;

use App\Models\File;

/**
 * Maps stored files to their canonical public download paths and nginx targets.
 */
final class FileLinkService
{
    private const NATIVE_UPLOAD_DIR = __DIR__ . '/../../uploads/';

    private static ?array $wpDomainsMap = null;

    public static function getWpDomainsMap(): array
    {
        if (self::$wpDomainsMap !== null) {
            return self::$wpDomainsMap;
        }

        $configPath = '/var/www/config/wp-domains.json';
        if (!is_file($configPath)) {
            $configPath = __DIR__ . '/../../config/wp-domains.json';
        }

        if (!is_file($configPath)) {
            return self::$wpDomainsMap = [];
        }

        $json = file_get_contents($configPath);
        $decoded = $json !== false ? json_decode($json, true) : null;
        if (!is_array($decoded)) {
            return self::$wpDomainsMap = [];
        }

        $map = [];
        foreach ($decoded as $domain => $root) {
            if (!is_string($domain) || !is_string($root) || $root === '') {
                continue;
            }
            $normalizedDomain = self::normalizeHost($domain);
            if ($normalizedDomain === '') {
                continue;
            }
            $map[$normalizedDomain] = rtrim($root, '/\\');
        }

        return self::$wpDomainsMap = $map;
    }

    public static function getNativeUploadRoot(): string
    {
        return rtrim(self::NATIVE_UPLOAD_DIR, '/\\');
    }

    public static function normalizeHost(string $host): string
    {
        $host = trim(strtolower($host));
        if ($host === '') {
            return '';
        }

        if (!preg_match(
            '/^(?<domain>[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?)(?::(?<port>[0-9]{1,5}))?$/D',
            $host,
            $matches
        )) {
            return '';
        }
        if (isset($matches['port']) && $matches['port'] !== '' && (int) $matches['port'] > 65535) {
            return '';
        }

        return $matches['domain'];
    }

    public static function getUploadHost(string $baseUrl): string
    {
        $host = parse_url($baseUrl, PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : '';
    }

    public static function isNativeUploadHost(string $requestHost, string $baseUrl): bool
    {
        $uploadHost = self::getUploadHost($baseUrl);
        return $uploadHost !== '' && self::normalizeHost($requestHost) === $uploadHost;
    }

    /**
     * Return the safe serving metadata for a database file, or null when its
     * physical path is outside all configured storage roots.
     */
    public static function classifyFile(File $file): ?array
    {
        $resolvedPath = realpath((string) $file->path);
        if ($resolvedPath === false || !is_file($resolvedPath)) {
            return null;
        }

        $nativeRelative = self::relativePathWithin($resolvedPath, self::getNativeUploadRoot());
        if ($nativeRelative !== null) {
            return [
                'kind' => 'native',
                'resolved_path' => $resolvedPath,
                'relative_path' => $nativeRelative,
                'public_path' => '/uploads/' . self::encodeRelativePath($nativeRelative),
                'internal_uri' => '/internal_uploads/' . self::encodeRelativePath($nativeRelative),
                'domain' => null,
            ];
        }

        $map = self::getWpDomainsMap();
        $recordedHost = self::normalizeHost((string) ($file->host ?? ''));
        if ($recordedHost !== '' && isset($map[$recordedHost])) {
            $classified = self::classifyWpFile($resolvedPath, $recordedHost, $map[$recordedHost]);
            if ($classified !== null) {
                return $classified;
            }
        }

        // Old records may have no host. Scanning configured roots keeps their ID
        // links working without trusting an arbitrary host stored in the database.
        foreach ($map as $domain => $root) {
            $classified = self::classifyWpFile($resolvedPath, $domain, $root);
            if ($classified !== null) {
                return $classified;
            }
        }

        return null;
    }

    public static function generateSecureFileLink(
        File $file,
        string $nativeBaseUrl,
        ?string $userIp = null,
        ?string $fileName = null
    ): string {
        $classification = self::classifyFile($file);
        if ($classification === null) {
            return SecureLinkService::generateSecureFileLink(
                (int) $file->id,
                $nativeBaseUrl,
                $userIp,
                $fileName
            );
        }

        return SecureLinkService::buildSecureLink(
            self::baseUrlForClassification($classification, $nativeBaseUrl),
            $classification['public_path'],
            time() + 24 * 60 * 60,
            $userIp ?? ''
        );
    }

    /**
     * Generate a canonical URL without access credentials. Admin consumers pass
     * this URL to the downstream service that adds its own authorization values.
     */
    public static function generateUnsignedFileLink(File $file, string $nativeBaseUrl): string
    {
        $classification = self::classifyFile($file);
        if ($classification === null) {
            return rtrim($nativeBaseUrl, '/') . '/files/serve/' . (int) $file->id;
        }

        return self::baseUrlForClassification($classification, $nativeBaseUrl)
            . $classification['public_path'];
    }

    public static function resolveNativePath(string $relativePath): ?array
    {
        $resolved = self::resolvePathWithin($relativePath, self::getNativeUploadRoot());
        if ($resolved !== null) {
            $resolved['internal_uri'] = '/internal_uploads/'
                . self::encodeRelativePath($resolved['relative_path']);
        }
        return $resolved;
    }

    /**
     * Resolve an existing native file to its canonical absolute path.
     * This also accepts legacy database paths containing safe ".." segments,
     * but only when the final file remains inside the native upload root.
     */
    public static function canonicalizeNativeFilePath(string $path): ?string
    {
        $resolvedPath = realpath($path);
        if ($resolvedPath === false || !is_file($resolvedPath)) {
            return null;
        }

        return self::relativePathWithin($resolvedPath, self::getNativeUploadRoot()) !== null
            ? $resolvedPath
            : null;
    }

    /**
     * Find the tracked native file for a resolved target. Older uploads stored
     * non-canonical absolute paths, so compare their safely resolved paths when
     * an exact canonical database lookup does not match.
     */
    public static function findNativeFile(array $target): ?File
    {
        $resolvedPath = $target['resolved_path'] ?? null;
        if (!is_string($resolvedPath) || $resolvedPath === '') {
            return null;
        }

        $file = File::where('path', $resolvedPath)->first();
        if ($file) {
            return $file;
        }

        $name = basename($resolvedPath);
        foreach (File::where('name', $name)->get() as $candidate) {
            $candidatePath = self::canonicalizeNativeFilePath((string) $candidate->path);
            if ($candidatePath !== null && self::pathsEqual($candidatePath, $resolvedPath)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function resolveWpPath(string $relativePath, string $host): ?array
    {
        $domain = self::normalizeHost($host);
        $map = self::getWpDomainsMap();
        if ($domain !== '' && isset($map[$domain])) {
            $resolved = self::resolvePathWithin($relativePath, $map[$domain]);
            if ($resolved !== null) {
                $resolved['domain'] = $domain;
                $resolved['internal_uri'] = '/internal_wp_' . self::sanitizeDomainForInternal($domain)
                    . '/' . self::encodeRelativePath($resolved['relative_path']);
                return $resolved;
            }
            return null;
        }

        $fallback = getenv('WP_UPLOADS_PATH');
        if ($fallback === false || $fallback === '') {
            return null;
        }

        $resolved = self::resolvePathWithin($relativePath, $fallback);
        if ($resolved !== null) {
            $resolved['domain'] = null;
            $resolved['internal_uri'] = '/internal_wp_default/'
                . self::encodeRelativePath($resolved['relative_path']);
        }
        return $resolved;
    }

    public static function encodeRelativePath(string $relativePath): string
    {
        $segments = explode('/', str_replace('\\', '/', $relativePath));
        return implode('/', array_map('rawurlencode', $segments));
    }

    private static function classifyWpFile(string $resolvedPath, string $domain, string $root): ?array
    {
        $relative = self::relativePathWithin($resolvedPath, $root);
        if ($relative === null) {
            return null;
        }

        return [
            'kind' => 'wordpress',
            'resolved_path' => $resolvedPath,
            'relative_path' => $relative,
            'public_path' => '/wp-content/uploads/' . self::encodeRelativePath($relative),
            'internal_uri' => '/internal_wp_' . self::sanitizeDomainForInternal($domain)
                . '/' . self::encodeRelativePath($relative),
            'domain' => $domain,
        ];
    }

    private static function resolvePathWithin(string $relativePath, string $root): ?array
    {
        if ($relativePath === '' || str_contains($relativePath, "\0") || str_contains($relativePath, '\\')) {
            return null;
        }

        $segments = explode('/', $relativePath);
        if (in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) {
            return null;
        }

        $rootReal = realpath($root);
        if ($rootReal === false || !is_dir($rootReal)) {
            return null;
        }

        $candidate = $rootReal . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
        $resolved = realpath($candidate);
        if ($resolved === false || !is_file($resolved)) {
            return null;
        }

        $relative = self::relativePathWithin($resolved, $rootReal);
        if ($relative === null) {
            return null;
        }

        return [
            'resolved_path' => $resolved,
            'relative_path' => $relative,
        ];
    }

    private static function relativePathWithin(string $path, string $root): ?string
    {
        $rootReal = realpath($root);
        $pathReal = realpath($path);
        if ($rootReal === false || $pathReal === false) {
            return null;
        }

        $rootWithSeparator = rtrim($rootReal, '/\\') . DIRECTORY_SEPARATOR;
        $matches = DIRECTORY_SEPARATOR === '\\'
            ? strncasecmp($pathReal, $rootWithSeparator, strlen($rootWithSeparator)) === 0
            : strncmp($pathReal, $rootWithSeparator, strlen($rootWithSeparator)) === 0;
        if (!$matches) {
            return null;
        }

        return str_replace('\\', '/', substr($pathReal, strlen($rootWithSeparator)));
    }

    private static function pathsEqual(string $left, string $right): bool
    {
        return DIRECTORY_SEPARATOR === '\\'
            ? strcasecmp($left, $right) === 0
            : strcmp($left, $right) === 0;
    }

    private static function sanitizeDomainForInternal(string $domain): string
    {
        return strtolower(str_replace('.', '_', $domain));
    }

    private static function baseUrlForClassification(array $classification, string $nativeBaseUrl): string
    {
        if ($classification['kind'] !== 'wordpress') {
            return rtrim($nativeBaseUrl, '/');
        }

        $scheme = parse_url($nativeBaseUrl, PHP_URL_SCHEME) ?: 'https';
        return $scheme . '://' . $classification['domain'];
    }
}
