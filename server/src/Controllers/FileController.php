<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\File;
use App\Models\DownloadLog;
use App\Service\MimeTypeMap;
use App\Service\SecureLinkService;
use App\Util\FormatHelper;
use App\Util\RequestHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FileController extends Controller
{
    private const MAX_FILE_SIZE = 21474836480; // 20GB in bytes
    private const UPLOAD_DIR = __DIR__ . '/../../uploads/';
    private const TUS_DATA_DIR = '/data/tus-data/';

    /**
     * Generate a secure download URL for a file by ID (flows from list/upload).
     * Delegates to SecureLinkService for compatibility with AdminController and external callers.
     */
    public static function generateSecureFileLink(int $fileId, string $baseUrl, ?string $userIp = null, ?string $fileName = null): string
    {
        return SecureLinkService::generateSecureFileLink($fileId, $baseUrl, $userIp, $fileName);
    }

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
     * Sanitize domain for nginx internal location prefix (must match generate-nginx-internal-wp.php).
     * Used for X-Accel-Redirect: /internal_wp_<sanitized>/...
     */
    private static function sanitizeDomainForInternal(string $domain): string
    {
        return strtolower(str_replace('.', '_', $domain));
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
    /**
     * Generate an HMAC-SHA256 signed upload token for TUS uploads.
     * Token encodes user_id, allowed file types, max size, and expiry.
     */
    public function createUploadToken(Request $request, Response $response): Response
    {
        $user = User::find($_SESSION['user_id']);
        if (!$user)
        {
            return $this->json($response, ['error' => 'User not found'], 404);
        }

        $payload = [
            'user_id' => $user->id,
            'allowed_types' => $user->allowedFileTypes ?? '',
            'max_size' => self::MAX_FILE_SIZE,
            'expires_at' => time() + 90000, // 25 hours
        ];

        $payloadJson = json_encode($payload);
        $payloadB64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $payloadB64, SecureLinkService::getHmacSecret());
        $token = $payloadB64 . '.' . $signature;

        return $this->json($response, ['token' => $token]);
    }

    /**
     * Decode and verify an HMAC-signed upload token. Returns payload array or null.
     */
    private static function verifyUploadToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2)
        {
            return null;
        }
        [$payloadB64, $signature] = $parts;
        $expected = hash_hmac('sha256', $payloadB64, SecureLinkService::getHmacSecret());
        if (!hash_equals($expected, $signature))
        {
            return null;
        }
        $padded = str_pad(strtr($payloadB64, '-_', '+/'), strlen($payloadB64) + (4 - strlen($payloadB64) % 4) % 4, '=');
        $json = base64_decode($padded, true);
        if ($json === false)
        {
            return null;
        }
        $payload = json_decode($json, true);
        if (!is_array($payload) || !isset($payload['expires_at']))
        {
            return null;
        }
        if ($payload['expires_at'] < time())
        {
            return null;
        }
        return $payload;
    }

    /**
     * Decode TUS Upload-Metadata header value (base64-encoded key-value pairs).
     */
    private static function parseTusMetadata(string $raw): array
    {
        $result = [];
        foreach (explode(',', $raw) as $pair)
        {
            $pair = trim($pair);
            if ($pair === '')
            {
                continue;
            }
            $parts = explode(' ', $pair, 2);
            $key = $parts[0];
            $value = isset($parts[1]) ? base64_decode($parts[1], true) : '';
            if ($value === false)
            {
                $value = '';
            }
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Helper: build a tusd hook rejection response (RejectUpload + HTTPResponse).
     * tusd v2 expects this format — returning a non-200 HTTP status alone does not reject.
     */
    private function tusReject(Response $response, string $message, int $statusCode = 400): Response
    {
        return $this->json($response, [
            'RejectUpload' => true,
            'HTTPResponse' => [
                'StatusCode' => $statusCode,
                'Body' => json_encode(['error' => $message]),
                'Header' => ['Content-Type' => 'application/json'],
            ],
        ]);
    }

    /**
     * Handle TUS hook events from tusd (pre-create, post-finish).
     */
    public function tusHooks(Request $request, Response $response): Response
    {
        // Slim's body parsing middleware may have already consumed the stream,
        // so prefer getParsedBody(); fall back to reading the raw stream.
        $data = $request->getParsedBody();
        if (!is_array($data))
        {
            $body = (string)$request->getBody();
            $data = json_decode($body, true);
        }
        if (!is_array($data))
        {
            return $this->json($response, ['ok' => true]);
        }

        $type = $data['Type'] ?? '';
        $upload = $data['Event']['Upload'] ?? [];

        switch ($type)
        {
            case 'pre-create':
                return $this->tusPreCreate($response, $upload);
            case 'post-finish':
                return $this->tusPostFinish($response, $upload);
            default:
                return $this->json($response, ['ok' => true]);
        }
    }

    private function tusPreCreate(Response $response, array $upload): Response
    {
        $metaRaw = $upload['MetaData'] ?? [];
        $token = $metaRaw['token'] ?? '';
        $filename = $metaRaw['filename'] ?? '';
        $uploadSize = (int)($upload['Size'] ?? 0);

        if ($token === '')
        {
            return $this->tusReject($response, 'Missing upload token', 403);
        }

        $payload = self::verifyUploadToken($token);
        if ($payload === null)
        {
            return $this->tusReject($response, 'Invalid or expired upload token', 403);
        }

        if ($uploadSize > ($payload['max_size'] ?? self::MAX_FILE_SIZE))
        {
            return $this->tusReject($response, 'File too large');
        }

        if ($uploadSize === 0)
        {
            return $this->tusReject($response, 'File is empty');
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === '')
        {
            return $this->tusReject($response, 'File has no extension');
        }

        $allowedTypes = $this->parseAllowedFileTypes($payload['allowed_types'] ?? '');
        if (!empty($allowedTypes) && !in_array($extension, $allowedTypes))
        {
            return $this->tusReject($response, 'File type "' . $extension . '" not allowed. Allowed: ' . implode(', ', $allowedTypes));
        }

        return $this->json($response, ['ok' => true]);
    }

    private function tusPostFinish(Response $response, array $upload): Response
    {
        $uploadId = $upload['ID'] ?? '';
        $metaRaw = $upload['MetaData'] ?? [];
        $token = $metaRaw['token'] ?? '';
        $filename = $metaRaw['filename'] ?? 'unknown';
        $fileSize = (int)($upload['Size'] ?? 0);

        // Use the file path from tusd's Storage info; fall back to constructing it
        $tusFilePath = $upload['Storage']['Path'] ?? (self::TUS_DATA_DIR . $uploadId);
        $tusInfoPath = ($upload['Storage']['InfoPath'] ?? '') ?: ($tusFilePath . '.info');

        $payload = self::verifyUploadToken($token);
        if ($payload === null)
        {
            error_log('[TUS post-finish] Invalid token for upload ' . $uploadId);
            return $this->json($response, ['ok' => true]);
        }

        $userId = $payload['user_id'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'bin';
        $sanitizedFilename = $this->generateSafeFilename($filename, $extension);

        $userUploadDir = self::UPLOAD_DIR . $userId . '/';
        $datePath = date('Y/m/d') . '/';
        $datedUploadDir = $userUploadDir . $datePath;
        if (!is_dir($datedUploadDir))
        {
            mkdir($datedUploadDir, 0755, true);
        }

        $targetPath = $datedUploadDir . $sanitizedFilename;

        // Skip file_exists() — PHP's realpath/stat cache is unreliable for files
        // created by external processes. Just attempt the move directly.
        $moved = @rename($tusFilePath, $targetPath);
        if (!$moved)
        {
            $moved = @copy($tusFilePath, $targetPath);
            if ($moved)
            {
                @unlink($tusFilePath);
            }
        }

        if (!$moved)
        {
            error_log('[TUS post-finish] Failed to move ' . $tusFilePath . ' -> ' . $targetPath . ' error: ' . error_get_last()['message'] ?? 'unknown');
            return $this->json($response, ['ok' => true]);
        }

        if (file_exists($tusInfoPath))
        {
            @unlink($tusInfoPath);
        }

        try
        {
            $fileRecord = File::create([
                'owner' => $userId,
                'name' => $sanitizedFilename,
                'type' => $extension,
                'size' => $fileSize,
                'path' => $targetPath,
            ]);
        }
        catch (\Exception $e)
        {
            error_log('[TUS post-finish] DB error: ' . $e->getMessage());
            if (file_exists($targetPath))
            {
                @unlink($targetPath);
            }
            return $this->json($response, ['ok' => true]);
        }

        $baseUrl = (string)($_ENV['UPLOAD_URL'] ?? '');
        $downloadUrl = SecureLinkService::generateSecureFileLink($fileRecord->id, $baseUrl, null, $filename);

        $resultPath = self::TUS_DATA_DIR . $uploadId . '.result.json';
        file_put_contents($resultPath, json_encode([
            'file_id' => $fileRecord->id,
            'original_name' => $filename,
            'stored_name' => $sanitizedFilename,
            'size' => $fileSize,
            'extension' => $extension,
            'uploaded_at' => (string)$fileRecord->created_at,
            'download_url' => $downloadUrl,
        ]));

        return $this->json($response, ['ok' => true]);
    }

    /**
     * Get the result of a completed TUS upload (download URL etc.).
     * Called by the client after tus-js-client reports success.
     */
    public function getTusUploadResult(Request $request, Response $response, array $args): Response
    {
        $uploadId = $args['uploadId'] ?? '';
        if ($uploadId === '' || preg_match('/[^a-zA-Z0-9_+-]/', $uploadId))
        {
            return $this->json($response, ['error' => 'Invalid upload ID'], 400);
        }

        $resultPath = self::TUS_DATA_DIR . $uploadId . '.result.json';
        if (!file_exists($resultPath))
        {
            return $this->json($response, ['error' => 'Upload result not found'], 404);
        }

        $result = json_decode(file_get_contents($resultPath), true);
        if (!is_array($result))
        {
            return $this->json($response, ['error' => 'Corrupt result file'], 500);
        }

        // Verify the requesting user owns this file
        $user = User::find($_SESSION['user_id'] ?? 0);
        if (!$user)
        {
            return $this->json($response, ['error' => 'Unauthorized'], 401);
        }

        $fileRecord = File::find($result['file_id'] ?? 0);
        if (!$fileRecord || (int)$fileRecord->owner !== (int)$user->id)
        {
            return $this->json($response, ['error' => 'Unauthorized'], 403);
        }

        // Refresh the download URL with current client IP
        $baseUrl = (string)($_ENV['UPLOAD_URL'] ?? '');
        $clientIp = RequestHelper::getClientIp($request) ?: null;
        $fileName = $result['original_name'] ?? $fileRecord->name;
        $result['download_url'] = SecureLinkService::generateSecureFileLink($fileRecord->id, $baseUrl, $clientIp, $fileName);

        // Clean up result file after retrieval
        @unlink($resultPath);

        return $this->json($response, ['file' => $result]);
    }

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
                'error' => 'File size exceeds maximum allowed size of ' . (FormatHelper::formatBytes(self::MAX_FILE_SIZE))
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
        if (!MimeTypeMap::isValidMimeType($mimeType, $fileExtension))
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
        $host = $request->getHeaderLine('Host');
        try
        {
            $fileRecord = File::create([
                'owner' => $user->id,
                'name' => $sanitizedFilename,
                'type' => $fileExtension,
                'size' => $fileSize,
                'path' => $targetPath,
                'host' => $host !== '' ? $host : null,
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
                'download_url' => SecureLinkService::generateSecureFileLink(
                    $fileRecord->id,
                    (string)($_ENV['UPLOAD_URL'] ?? ''),
                    RequestHelper::getClientIp($request) ?: null,
                    $filename
                )
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

    /**
     * Get list of valid MIME types for admin reference
     */
    public function getValidMimeTypes(Request $request, Response $response): Response
    {
        $mimeMap = MimeTypeMap::getMap();
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
            ->map(function ($file) use ($user, $request)
            {
                $baseUrl = (string)($_ENV['UPLOAD_URL'] ?? '');
                $userIp = RequestHelper::getClientIp($request) ?: null;
                return [
                    'id' => $file->id,
                    'filename' => $file->name,
                    'original_filename' => $file->name, // Since we don't store original separately
                    'file_size' => $file->size,
                    'file_size_formatted' => FormatHelper::formatBytes($file->size),
                    'mime_type' => $file->type,
                    'file_path' => $file->path,
                    'download_url' => SecureLinkService::generateSecureFileLink($file->id, $baseUrl, $userIp, $file->name),
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
     * Render a beautiful Persian HTML error page for file-serving errors.
     * SEO-friendly with proper meta, lang, and semantic structure.
     */
    private function renderErrorPage(Response $response, int $statusCode, string $title, string $description, string $hint = ''): Response
    {
        $statusMessages = [
            400 => 'درخواست نامعتبر',
            403 => 'عدم دسترسی',
            404 => 'صفحه‌ایافت نشد',
            500 => 'خطای سرور',
        ];
        $statusLabel = $statusMessages[$statusCode] ?? 'خطا';
        $html = '<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">
    <title>' . htmlspecialchars($title . ' | ' . $statusLabel, ENT_QUOTES, 'UTF-8') . '</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Vazirmatn", "Tahoma", sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: #e8e8e8;
            padding: 1.5rem;
            line-height: 1.7;
        }
        .card {
            max-width: 480px;
            width: 100%;
            background: rgba(255,255,255,0.05);
            border-radius: 1.25rem;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.08);
            text-align: center;
        }
        .icon { font-size: 3.5rem; margin-bottom: 1rem; opacity: 0.9; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; }
        p { font-size: 1rem; color: #b8b8b8; margin-bottom: 1rem; }
        .hint { font-size: 0.9rem; color: #8892a6; margin-top: 1rem; }
        .status { display: inline-block; background: rgba(239,68,68,0.2); color: #fca5a5; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.85rem; margin-bottom: 1.25rem; }
    </style>
</head>
<body>
    <main class="card" role="main">
        <div class="status" aria-hidden="true">' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</div>
        <div class="icon" aria-hidden="true">⚠️</div>
        <h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>
        <p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>
        ' . ($hint !== '' ? '<p class="hint">' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</p>' : '') . '
    </main>
</body>
</html>';
        $response->getBody()->write($html);
        return $response
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withStatus($statusCode);
    }

    /**
     * Build Content-Disposition header value for download (RFC 5987-aware).
     * Ensures download managers like IDM get a proper filename.
     */
    private function buildContentDisposition(string $filename): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $filename);
        $value = 'attachment; filename="' . $escaped . '"';
        if (preg_match('/[^\x20-\x7E]/', $filename))
        {
            $value .= '; filename*=UTF-8\'\'' . rawurlencode($filename);
        }
        return $value;
    }

    /**
     * Serve file by ID with secure link (md5 + expires). Compatible with WordPress/nginx secure_link.
     * URL: /files/serve/{id}?md5=...&expires=... or /files/serve?id=...&md5=...&expires=...
     */
    public function serveFile(Request $request, Response $response, array $args = []): Response
    {
        $params = $request->getQueryParams();
        $fileId = $args['id'] ?? $params['id'] ?? null;
        $providedMd5 = $params['md5'] ?? null;
        $expires = $params['expires'] ?? null;

        if (!$fileId || !$providedMd5 || $expires === null || $expires === '')
        {
            return $this->renderErrorPage($response, 400,
                'لینک دانلود ناقص است',
                'برای دانلود فایل، لینک کامل با پارامترهای امنیتی لازم است. لطفاً وارد حساب خود در تاپ جی اس ام شوید و دوباره روی دانلود کلیک کنید.',
                'اگر از صفحهٔ سایت به اینجا آمده‌اید، لینک ممکن است نادرست کپی شده باشد.');
        }

        $path = '/files/serve/' . $fileId;
        $userIp = RequestHelper::getClientIp($request);

        if (!SecureLinkService::verifySecureLink($path, $providedMd5, $expires, $userIp))
        {
            return $this->renderErrorPage($response, 403,
                'لینک دانلود منقضی یا نامعتبر است',
                'این لینک دیگر قابل استفاده نیست. لینک‌های دانلود پس از مدتی منقضی می‌شوند.',
                'لطفاً دوباره از صفحهٔ دانلود ها، لینک دانلود جدید بگیرید.');
        }

        // Find file by ID
        $file = File::find($fileId);
        if (!$file)
        {
            return $this->renderErrorPage($response, 404,
                'فایل یافت نشد',
                'فایلی با این شناسه در سیستم وجود ندارد. ممکن است فایل حذف شده یا شناسه اشتباه باشد.',
                'لطفا با پشتیبانی تماس بگیرید');
        }

        // Check if file exists on disk
        if (!file_exists($file->path))
        {
            return $this->renderErrorPage($response, 404,
                'فایل روی دیسک یافت نشد',
                'رکورد فایل موجود است اما فایل فیزیکی روی سرور پیدا نشد. احتمالاً فایل حذف یا جابجا شده است.',
                'لطفا با پشتیبانی تماس بگیرید');
        }

        // Log the download (don't let logging failure block the download)
        try
        {
            DownloadLog::create([
                'file_id' => $file->id,
                'ip_address' => RequestHelper::getClientIp($request) ?: null,
                'user_agent' => $request->getHeaderLine('User-Agent') ?: null,
            ]);
        }
        catch (\Exception $e)
        {
            // Silently fail — serving the file is more important than logging
        }

        // Redirect to nginx internal location so nginx serves the file directly (no PHP streaming)
        $uploadDirReal = realpath(self::UPLOAD_DIR);
        if ($uploadDirReal === false || !is_dir($uploadDirReal))
        {
            return $this->renderErrorPage($response, 500,
                'پوشهٔ آپلود در دسترس نیست',
                'سرور نمی‌تواند به پوشهٔ فایل‌های آپلود شده دسترسی پیدا کند. این مشکل موقتی است.',
                'لطفاً چند دقیقه بعد دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.');
        }
        $pathReal = realpath($file->path);
        if ($pathReal === false || !is_file($pathReal))
        {
            return $this->renderErrorPage($response, 404,
                'فایل روی دیسک یافت نشد',
                'فایل فیزیکی در مسیر ذخیره‌سازی پیدا نشد. ممکن است حذف یا منتقل شده باشد.',
                'لطفا با پشتیبانی تماس بگیرید');
        }
        $baseWithSep = $uploadDirReal . DIRECTORY_SEPARATOR;
        if ($pathReal !== $uploadDirReal && strpos($pathReal, $baseWithSep) !== 0)
        {
            return $this->renderErrorPage($response, 403,
                'مسیر فایل معتبر نیست',
                'مسیر فایل خارج از محدودهٔ مجاز است. تلاش برای دسترسی غیرمجاز شناسایی شد.',
                'لطفا با پشتیبانی تماس بگیرید');
        }
        $relativePath = str_replace([$baseWithSep, '\\'], ['', '/'], $pathReal);
        $internalUri = '/internal_uploads/' . $relativePath;

        $response = $response->withHeader('X-Accel-Redirect', $internalUri);
        $response = $response->withHeader('X-Accel-Buffering', 'no');
        $response = $response->withHeader('Content-Length', '0');
        $response->getBody()->write('');
        return $response;
    }

    /**
     * Serve file from wp-content/uploads path. Creates a file record and logs download if not yet in DB.
     * URL pattern: /wp-content/uploads/2026/02/filename.rar or /uploads/2026/02/filename.rar.
     * Requires query params md5 and expires (same as file serve); IP is checked when present via the same optional-IP verification.
     */
    public function serveWpContentFile(Request $request, Response $response, array $args): Response
    {
        $requestedPath = $args['path'] ?? '';
        if ($requestedPath === '')
        {
            return $this->renderErrorPage($response, 400,
                'مسیر فایل مشخص نشده',
                'آدرس فایل برای دانلود ارسال نشده است. لطفاً از لینک اصلی سایت استفاده کنید.',
                'اگر از جای دیگری این لینک را کپی کرده‌اید، لینک ناقص است.');
        }

        // Reject null bytes (directory injection / legacy PHP path issues)
        if (strpos($requestedPath, "\0") !== false)
        {
            return $this->renderErrorPage($response, 400,
                'مسیر فایل نامعتبر است',
                'آدرس درخواستی شامل کاراکترهای غیرمجاز است و قابل پردازش نیست.',
                'لطفاً از لینک صحیح صفحهٔ سایت استفاده کنید.');
        }

        $params = $request->getQueryParams();
        $providedMd5 = $params['md5'] ?? null;
        $expires = $params['expires'] ?? null;
        if ($providedMd5 === null || $providedMd5 === '' || $expires === null || $expires === '')
        {
            return $this->renderErrorPage($response, 400,
                'لینک دانلود ناقص است',
                'برای دانلود فایل، پارامترهای امنیتی (md5 و expires) لازم است. لطفاً از صفحهٔ اصلی سایت لینک بگیرید.',
                'وارد اکانت خود در تاپ جی اس ام شوید و دوباره روی دانلود کلیک کنید.');
        }

        $pathForHash = $request->getUri()->getPath();
        $userIp = RequestHelper::getClientIp($request);
        if (!SecureLinkService::verifySecureLink($pathForHash, $providedMd5, $expires, $userIp))
        {
            return $this->renderErrorPage($response, 403,
                'لینک دانلود منقضی یا نامعتبر است',
                'این لینک امنیتی دیگر معتبر نیست یا منقضی شده. لینک‌های محافظت‌شده پس از مدتی غیرفعال می‌شوند.',
                'وارد اکانت خود در تاپ جی اس ام شوید و دوباره روی دانلود کلیک کنید.');
        }

        $host = $request->getHeaderLine('Host');
        $basePath = self::getWpUploadsBase($host);
        $baseReal = realpath($basePath);
        if ($baseReal === false || !is_dir($baseReal))
        {
            return $this->renderErrorPage($response, 404,
                'پوشهٔ آپلود در دسترس نیست',
                'پوشهٔ ذخیرهٔ فایل‌های وردپرس برای این دامنه یافت نشد یا قابل دسترسی نیست.',
                'لطفا با پشتیبانی تماس بگیرید');
        }

        $baseWithSep = $baseReal . DIRECTORY_SEPARATOR;
        $pathWithBase = $baseWithSep . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $requestedPath);
        $resolved = realpath($pathWithBase);
        if ($resolved === false || !is_file($resolved))
        {
            return $this->renderErrorPage($response, 404,
                'فایل یافت نشد',
                'فایل درخواستی در مسیر آپلودها وجود ندارد. ممکن است فایل حذف شده یا آدرس اشتباه باشد.',
                'لطفا با پشتیبانی تماس بگیرید');
        }

        // Strict directory containment: resolved must be exactly base or under it (prevents e.g. base="uploads" matching "uploads_backup/..")
        if ($resolved !== $baseReal && strpos($resolved, $baseWithSep) !== 0)
        {
            return $this->renderErrorPage($response, 403,
                'مسیر فایل معتبر نیست',
                'مسیر فراتر از محدودهٔ مجاز آپلودها است. دسترسی غیرمجاز تشخیص داده شد.',
                'لطفا با پشتیبانی تماس بگیرید');
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
                'host' => $host !== '' ? $host : null,
            ]);
        }

        try
        {
            DownloadLog::create([
                'file_id' => $file->id,
                'ip_address' => RequestHelper::getClientIp($request) ?: null,
                'user_agent' => $request->getHeaderLine('User-Agent') ?: null,
            ]);
        }
        catch (\Exception $e)
        {
            // Don't block the download
        }

        // Redirect to nginx internal location (per-domain) so nginx serves the file directly
        $domain = strtolower(explode(':', $host)[0]);
        $map = self::loadWpDomainsMap();
        if ($domain !== '' && isset($map[$domain]))
        {
            $prefix = 'internal_wp_' . self::sanitizeDomainForInternal($domain);
        }
        else
        {
            $prefix = 'internal_wp_default';
        }
        $relativePath = str_replace([$baseWithSep, '\\'], ['', '/'], $resolved);
        $internalUri = '/' . $prefix . '/' . $relativePath;

        $response = $response->withHeader('X-Accel-Redirect', $internalUri);
        $response = $response->withHeader('X-Accel-Buffering', 'no');
        $response = $response->withHeader('Content-Length', '0');
        $response->getBody()->write('');
        return $response;
    }

}
