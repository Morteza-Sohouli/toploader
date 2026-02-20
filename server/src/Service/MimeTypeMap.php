<?php

namespace App\Service;

/**
 * Single source of truth for file extension → MIME type(s) mapping.
 */
final class MimeTypeMap
{
    /** @var array<string, array<string>> extension => list of MIME types */
    private const MAP = [
        'jpg' => ['image/jpeg', 'image/jpg'],
        'jpeg' => ['image/jpeg', 'image/jpg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'bmp' => ['image/bmp', 'image/x-bmp'],
        'tiff' => ['image/tiff', 'image/x-tiff'],
        'svg' => ['image/svg+xml'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'odt' => ['application/vnd.oasis.opendocument.text'],
        'rtf' => ['application/rtf', 'text/rtf'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'json' => ['application/json'],
        'xml' => ['application/xml', 'text/xml'],
        'html' => ['text/html'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'rar' => ['application/vnd.rar', 'application/x-rar-compressed', 'application/x-rar', 'application/octet-stream', 'application/x-compressed'],
        '7z' => ['application/x-7z-compressed'],
        'tar' => ['application/x-tar'],
        'gz' => ['application/gzip', 'application/x-gzip'],
        'mp4' => ['video/mp4'],
        'avi' => ['video/x-msvideo', 'video/avi'],
        'mov' => ['video/quicktime'],
        'wmv' => ['video/x-ms-wmv'],
        'mp3' => ['audio/mpeg'],
        'wav' => ['audio/wav', 'audio/x-wav'],
        'flac' => ['audio/flac'],
        'aac' => ['audio/aac'],
    ];

    /**
     * Whether the given MIME type is valid for the extension (permissive if extension not in map).
     */
    public static function isValidMimeType(?string $mimeType, string $extension): bool
    {
        $extension = strtolower($extension);
        if (!isset(self::MAP[$extension])) {
            return true;
        }
        return in_array($mimeType, self::MAP[$extension], true);
    }

    /**
     * Full map for admin/API: extension => list of MIME types.
     *
     * @return array<string, array<string>>
     */
    public static function getMap(): array
    {
        return self::MAP;
    }

    /**
     * Primary MIME type for extension (first in list), or null if unknown.
     */
    public static function getPrimaryMime(string $extension): ?string
    {
        $mimes = self::MAP[strtolower($extension)] ?? null;
        return $mimes !== null ? $mimes[0] : null;
    }
}
