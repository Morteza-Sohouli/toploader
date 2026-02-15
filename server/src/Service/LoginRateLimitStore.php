<?php

namespace App\Service;

class LoginRateLimitStore
{
    private const MAX_ATTEMPTS = 3;

    /** Block duration in seconds (15 minutes) */
    private const BLOCK_DURATION = 900;

    private static function getFilePath(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/login_attempts.json';
    }

    private static function read(): array
    {
        $path = self::getFilePath();
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private static function write(array $data): void
    {
        $path = self::getFilePath();
        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return;
        }
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    /**
     * @return array{count: int, blocked_at?: int}
     */
    private static function getEntry(string $ip): array
    {
        $data = self::read();
        $entry = $data[$ip] ?? null;
        if (is_array($entry)) {
            return [
                'count' => (int) ($entry['count'] ?? 0),
                'blocked_at' => isset($entry['blocked_at']) ? (int) $entry['blocked_at'] : null,
            ];
        }
        return ['count' => (int) $entry, 'blocked_at' => null];
    }

    public static function getFailCount(string $ip): int
    {
        return self::getEntry($ip)['count'];
    }

    public static function isBlocked(string $ip): bool
    {
        $entry = self::getEntry($ip);
        if ($entry['count'] < self::MAX_ATTEMPTS) {
            return false;
        }
        $blockedAt = $entry['blocked_at'] ?? 0;
        if ($blockedAt === 0) {
            return true;
        }
        return (time() - $blockedAt) < self::BLOCK_DURATION;
    }

    public static function recordFailedAttempt(string $ip): void
    {
        $data = self::read();
        $entry = $data[$ip] ?? ['count' => 0];
        if (!is_array($entry)) {
            $entry = ['count' => (int) $entry];
        }
        $blockedAt = isset($entry['blocked_at']) ? (int) $entry['blocked_at'] : null;
        if ($blockedAt !== null && (time() - $blockedAt) >= self::BLOCK_DURATION) {
            $entry = ['count' => 0];
        }
        $count = (int) ($entry['count'] ?? 0) + 1;
        $entry['count'] = $count;
        if ($count >= self::MAX_ATTEMPTS) {
            $entry['blocked_at'] = time();
        }
        $data[$ip] = $entry;
        self::write($data);
    }

    public static function reset(string $ip): void
    {
        $data = self::read();
        unset($data[$ip]);
        self::write($data);
    }

    public static function getMaxAttempts(): int
    {
        return self::MAX_ATTEMPTS;
    }
}
