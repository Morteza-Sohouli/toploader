<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\File;
use App\Models\DownloadLog;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FileController extends Controller
{
    private const MAX_FILE_SIZE = 21474836480; // 20GB in bytes
    private const UPLOAD_DIR = __DIR__ . '/../../uploads/';
    private const HASH_SALT = 'your-slsjfos8s8ohs8fhos;8dfs8fs8afoafsp;production'; // Change this to a secure random string

    /** Cached domain-to-uploads-path map loaded from config/wp-domains.json */
    private static ?array $wpDomainsMap = null;

    /**
     * Load the domain → uploads-path mapping from wp-domains.json (cached after first call).
     */
    private static function loadWpDomainsMap(): array
    {
        if (self::$wpDomainsMap === null)
        {
            // Mounted into the container at /var/www/config/wp-domains.json
            $configPath = '/var/www/config/wp-domains.json';
            if (!is_file($configPath))
            {
                // Fallback: relative to project root (local dev without Docker)
                $configPath = __DIR__ . '/../../config/wp-domains.json';
            }
            if (is_file($configPath))
            {
                $json = file_get_contents($configPath);
                self::$wpDomainsMap = json_decode($json, true) ?: [];
            }
            else
            {
                self::$wpDomainsMap = [];
            }
        }
        return self::$wpDomainsMap;
    }

    /**
     * Resolve the WP uploads base directory for the given request host.
     * Looks up the host in config/wp-domains.json; falls back to WP_UPLOADS_PATH env or default path.
     */
    private static function getWpUploadsBase(string $host = ''): string
    {
        $map = self::loadWpDomainsMap();

        // Strip port if present (e.g. "domain.com:443" → "domain.com")
        $domain = strtolower(explode(':', $host)[0]);

        if ($domain !== '' && isset($map[$domain]))
        {
            return rtrim($map[$domain], '/\\');
        }

        // Fallback to single-path env var (backward compatibility)
        $base = getenv('WP_UPLOADS_PATH');
        return $base !== false && $base !== '' ? rtrim($base, '/\\') : (__DIR__ . '/../../wp-content/uploads');
    }
    // Note: Ensure PHP configuration supports large uploads:
    // php.ini: upload_max_filesize=20G, post_max_size=20G, memory_limit=512M, max_execution_time=3600

    public function upload(Request $request, Response $response): Response
    {
        // Get authenticated user (authentication already verified by middleware)
        $user = User::find($_SESSION['user_id']);
        if (!$user)
        {
            return $this->json($response, ['error' => 'User not found'], 404);
        }

        // Get uploaded files
        $uploadedFiles = $request->getUploadedFiles();

        if (empty($uploadedFiles['file']))
        {
            return $this->json($response, ['error' => 'No file uploaded'], 400);
        }

        $uploadedFile = $uploadedFiles['file'];

        // Check for upload errors
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK)
        {
            return $this->json($response, ['error' => 'File upload error: ' . $this->getUploadErrorMessage($uploadedFile->getError())], 400);
        }

        // Get file details
        $filename = $uploadedFile->getClientFilename();
        $fileSize = $uploadedFile->getSize();

        // Validate file size
        if ($fileSize > self::MAX_FILE_SIZE)
        {
            return $this->json($response, [
                'error' => 'File size exceeds maximum allowed size of ' . ($this->formatBytes(self::MAX_FILE_SIZE))
            ], 400);
        }

        if ($fileSize === 0)
        {
            return $this->json($response, ['error' => 'Uploaded file is empty'], 400);
        }

        // Get file extension
        $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (empty($fileExtension))
        {
            return $this->json($response, ['error' => 'File has no extension'], 400);
        }

        // Validate file type against user's allowed file types
        $allowedFileTypes = $this->parseAllowedFileTypes($user->allowedFileTypes);

        if (!in_array($fileExtension, $allowedFileTypes))
        {
            return $this->json($response, [
                'error' => 'File type "' . $fileExtension . '" is not allowed. Allowed types: ' . implode(', ', $allowedFileTypes)
            ], 400);
        }

        // Additional MIME type validation for extra security
        $mimeType = $uploadedFile->getClientMediaType();
        if (!$this->isValidMimeType($mimeType, $fileExtension))
        {
            return $this->json($response, [
                'error' => 'File MIME type does not match extension'
            ], 400);
        }

        // Create upload directory if it doesn't exist
        if (!is_dir(self::UPLOAD_DIR))
        {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }

        // Create user-specific subdirectory
        $userUploadDir = self::UPLOAD_DIR . $user->id . '/';
        if (!is_dir($userUploadDir))
        {
            mkdir($userUploadDir, 0755, true);
        }

        // Create date-based subdirectory: YYYY/MM/DD
        $datePath = date('Y/m/d') . '/';
        $datedUploadDir = $userUploadDir . $datePath;
        if (!is_dir($datedUploadDir))
        {
            mkdir($datedUploadDir, 0755, true);
        }

        // Generate unique, sanitized filename
        $sanitizedFilename = $this->generateSafeFilename($filename, $fileExtension);
        $targetPath = $datedUploadDir . $sanitizedFilename;

        // Move uploaded file using streaming (memory-efficient for large files)
        try
        {
            $uploadedFile->moveTo($targetPath);
        }
        catch (\Exception $e)
        {
            return $this->json($response, [
                'error' => 'Failed to save file: ' . $e->getMessage()
            ], 500);
        }

        // Save file metadata to database
        try
        {
            $fileRecord = File::create([
                'owner' => $user->id,
                'name' => $sanitizedFilename,
                'type' => $fileExtension,
                'size' => $fileSize,
                'path' => $targetPath,
            ]);
        }
        catch (\Exception $e)
        {
            // If database save fails, try to delete the uploaded file
            if (file_exists($targetPath))
            {
                unlink($targetPath);
            }
            return $this->json($response, [
                'error' => 'Failed to save file metadata: ' . $e->getMessage()
            ], 500);
        }

        // Return success response with file details
        return $this->json($response, [
            'message' => 'File uploaded successfully',
            'file' => [
                'id' => $fileRecord->id,
                'original_name' => $filename,
                'stored_name' => $sanitizedFilename,
                'size' => $fileSize,
                'extension' => $fileExtension,
                'mime_type' => $mimeType,
                'uploaded_at' => $fileRecord->created_at,
                'download_url' => $_ENV['UPLOAD_URL'] . '/files/serve?id=' . $fileRecord->id . '&hash=' . hash('sha256', $fileRecord->id . self::HASH_SALT)
            ]
        ], 201);
    }

    /**
     * Parse comma-separated allowed file types
     */
    private function parseAllowedFileTypes(?string $allowedFileTypes): array
    {
        if (empty($allowedFileTypes))
        {
            return [];
        }

        return array_map('trim', array_map('strtolower', explode(',', $allowedFileTypes)));
    }

    /**
     * Generate a safe, unique filename
     */
    private function generateSafeFilename(string $originalFilename, string $extension): string
    {
        // Remove extension from original filename
        $basename = pathinfo($originalFilename, PATHINFO_FILENAME);

        // Sanitize filename: remove special characters, keep only alphanumeric, dash, underscore
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        $sanitized = preg_replace('/_+/', '_', $sanitized); // Replace multiple underscores with single
        $sanitized = trim($sanitized, '_'); // Remove leading/trailing underscores

        // Limit length
        $sanitized = substr($sanitized, 0, 100);

        // Add timestamp and random string for uniqueness
        $uniquePart = time() . '_' . bin2hex(random_bytes(8));

        return $sanitized . '_' . $uniquePart . '.' . $extension;
    }

    /**
     * Validate MIME type matches file extension
     */
    private function isValidMimeType(?string $mimeType, string $extension): bool
    {
        // Common MIME type mappings
        $mimeMap = [
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

        if (!isset($mimeMap[$extension]))
        {
            // If extension not in map, allow it (permissive approach)
            return true;
        }

        return in_array($mimeType, $mimeMap[$extension]);
    }

    /**
     * Get human-readable error message for upload error codes
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode)
        {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE directive in HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload';
            default:
                return 'Unknown upload error';
        }
    }

    /**     * Get list of valid MIME types for admin reference
     */
    public function getValidMimeTypes(Request $request, Response $response): Response
    {
        // Common MIME type mappings
        $mimeMap = [
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

        return $this->json($response, [
            'valid_mime_types' => $mimeMap,
            'total_extensions' => count($mimeMap)
        ]);
    }

    /**
     * Get user's uploaded files with pagination
     */
    public function getUserUploads(Request $request, Response $response): Response
    {
        // Get authenticated user
        $user = \App\Models\User::find($_SESSION['user_id']);
        if (!$user)
        {
            return $this->json($response, ['error' => 'User not found'], 404);
        }

        // Get pagination parameters
        $params = $request->getQueryParams();
        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 30);
        $search = $params['search'] ?? null;
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;

        // Validate parameters
        $page = max(1, $page);
        $limit = max(1, min(100, $limit)); // Max 100 per page

        // Calculate offset
        $offset = ($page - 1) * $limit;

        // Build query with filters
        $query = \App\Models\File::where('owner', $user->id);

        // Apply search filter
        if ($search !== null && trim($search) !== '')
        {
            $query->where(function ($q) use ($search)
            {
                $q->where('name', 'LIKE', '%' . $search . '%');
            });
        }

        // Apply date range filters
        if ($fromDate !== null && trim($fromDate) !== '')
        {
            $query->where('created_at', '>=', $fromDate . ' 00:00:00');
        }

        if ($toDate !== null && trim($toDate) !== '')
        {
            $query->where('created_at', '<=', $toDate . ' 23:59:59');
        }

        // Get total count with filters
        $totalFiles = $query->count();

        // Get files with pagination
        $files = $query->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($file) use ($user)
            {
                return [
                    'id' => $file->id,
                    'filename' => $file->name,
                    'original_filename' => $file->name, // Since we don't store original separately
                    'file_size' => $file->size,
                    'file_size_formatted' => $this->formatBytes($file->size),
                    'mime_type' => $file->type,
                    'file_path' => $file->path,
                    'hash' => hash('sha256', $file->id . $user->id . self::HASH_SALT),
                    'created_at' => $file->created_at,
                    'updated_at' => $file->updated_at,
                ];
            });

        // Calculate pagination info
        $totalPages = ceil($totalFiles / $limit);

        return $this->json($response, [
            'files' => $files,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_files' => $totalFiles,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
            ]
        ]);
    }

    /**
     * Format bytes to human-readable size
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1024 ** $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Serve file by ID and hash for secure access
     */
    public function serveFile(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $fileId = $params['id'] ?? null;
        $providedHash = $params['hash'] ?? null;

        if (!$fileId || !$providedHash)
        {
            return $this->json($response, ['error' => 'File ID and hash are required'], 400);
        }

        // Generate expected hash using file ID, user ID, and salt
        $expectedHash = hash('sha256', $fileId . self::HASH_SALT);

        // Verify hash matches
        if ($expectedHash !== $providedHash)
        {
            return $this->json($response, ['error' => 'Invalid file access hash'], 403);
        }

        // Find file by ID
        $file = File::find($fileId);
        if (!$file)
        {
            return $this->json($response, ['error' => 'File not found'], 404);
        }


        // Check if file exists on disk
        if (!file_exists($file->path))
        {
            return $this->json($response, ['error' => 'File not found on disk'], 404);
        }

        // Log the download (don't let logging failure block the download)
        try
        {
            DownloadLog::create([
                'file_id' => $file->id,
                'ip_address' => $request->getServerParams()['REMOTE_ADDR'] ?? null,
                'user_agent' => $request->getHeaderLine('User-Agent') ?: null,
            ]);
        }
        catch (\Exception $e)
        {
            // Silently fail — serving the file is more important than logging
        }

        $mimeType = 'application/octet-stream'; // Default fallback
        // Set headers for file download
        $response = $response->withHeader('Content-Type', $mimeType);
        $response = $response->withHeader('Content-Length', filesize($file->path));
        $response = $response->withHeader('Content-Disposition', 'attachment; filename="' . $file->name . '"');
        $response = $response->withHeader('Cache-Control', 'private, max-age=0');

        // Stream the file
        $stream = fopen($file->path, 'rb');
        $response = $response->withBody(new \Slim\Psr7\Stream($stream));

        return $response;
    }

    /**
     * Serve file from wp-content/uploads path. Creates a file record and logs download if not yet in DB.
     * URL pattern: /wp-content/uploads/2026/02/filename.rar (query params like md5/expires are ignored).
     */
    public function serveWpContentFile(Request $request, Response $response, array $args): Response
    {
        $requestedPath = $args['path'] ?? '';
        if ($requestedPath === '')
        {
            return $this->json($response, ['error' => 'Path required'], 400);
        }

        // Reject null bytes (directory injection / legacy PHP path issues)
        if (strpos($requestedPath, "\0") !== false)
        {
            return $this->json($response, ['error' => 'Invalid path'], 400);
        }

        $host = $request->getHeaderLine('Host');
        $basePath = self::getWpUploadsBase($host);
        $baseReal = realpath($basePath);
        if ($baseReal === false || !is_dir($baseReal))
        {
            return $this->json($response, ['error' => 'Uploads directory not available'], 404);
        }

        $baseWithSep = $baseReal . DIRECTORY_SEPARATOR;
        $pathWithBase = $baseWithSep . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $requestedPath);
        $resolved = realpath($pathWithBase);
        if ($resolved === false || !is_file($resolved))
        {
            return $this->json($response, ['error' => 'File not found'], 404);
        }

        // Strict directory containment: resolved must be exactly base or under it (prevents e.g. base="uploads" matching "uploads_backup/..")
        if ($resolved !== $baseReal && strpos($resolved, $baseWithSep) !== 0)
        {
            return $this->json($response, ['error' => 'Invalid path'], 403);
        }

        $name = basename($resolved);
        $extension = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
        $size = filesize($resolved);

        $file = File::where('path', $resolved)->first();
        if (!$file)
        {
            $file = File::create([
                'owner' => 1,
                'name' => $name,
                'type' => $extension,
                'size' => $size,
                'path' => $resolved,
            ]);
        }

        try
        {
            DownloadLog::create([
                'file_id' => $file->id,
                'ip_address' => $request->getServerParams()['REMOTE_ADDR'] ?? null,
                'user_agent' => $request->getHeaderLine('User-Agent') ?: null,
            ]);
        }
        catch (\Exception $e)
        {
            // Don't block the download
        }

        $mimeType = $this->getMimeTypeFromExtension($extension) ?? 'application/octet-stream';
        $response = $response->withHeader('Content-Type', $mimeType);
        $response = $response->withHeader('Content-Length', (string)$size);
        $response = $response->withHeader('Content-Disposition', 'attachment; filename="' . addslashes($name) . '"');
        $response = $response->withHeader('Cache-Control', 'private, max-age=0');

        $stream = fopen($resolved, 'rb');
        $response = $response->withBody(new \Slim\Psr7\Stream($stream));

        return $response;
    }

    /**
     * Get MIME type from file extension
     */
    private function getMimeTypeFromExtension(string $extension): ?string
    {
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'tiff' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'rtf' => 'application/rtf',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'html' => 'text/html',
            'zip' => 'application/zip',
            'rar' => 'application/vnd.rar',
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'aac' => 'audio/aac',
        ];

        return $mimeMap[strtolower($extension)] ?? null;
    }
}
