<?php

namespace App\Util;

final class FormatHelper
{
    private const UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

    /**
     * Format bytes to human-readable size.
     */
    public static function formatBytes(int $bytes): string
    {
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count(self::UNITS) - 1);
        $bytes /= (1024 ** $pow);
        return round($bytes, 2) . ' ' . self::UNITS[$pow];
    }
}
