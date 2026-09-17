<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\File;
use App\Models\DownloadLog;
use App\Models\DeleteRequest;
use App\Service\MimeTypeMap;
use App\Service\FileLinkService;
use App\Service\SecureLinkService;
use App\Util\FormatHelper;
use App\Util\RequestHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FileController extends Controller
{
    private const MAX_FILE_SIZE = 30 * 1024 * 1024 * 1024; // 30GB in bytes
    private const UPLOAD_DIR = __DIR__ . '/../../uploads/';
    private const TUS_DATA_DIR = '/data/tus-data/';

    /** Generate the canonical path link, falling back to the legacy ID link. */
    public static function generateSecureFileLink(int $fileId, string $baseUrl, ?string $userIp = null, ?string $fileName = null): string
    {
        $file = File::find($fileId);
        if ($file) {
            return FileLinkService::generateSecureFileLink($file, $baseUrl, $userIp, $fileName);
        }
        return SecureLinkService::generateSecureFileLink($fileId, $baseUrl, $userIp, $fileName);
    }

    /**
     * Decode and normalize the uploads path for filesystem lookup.
     * Handles percent-encoded UTF-8 filenames and restores woocommerce_uploads/ when a
     * dedicated route stripped that prefix from the captured segment.
     */
    private static function normalizeWpUploadPath(string $requestedPath, string $uriPath): string
    {
        $requestedPath = rawurldecode($requestedPath);
        $uriPath = rawurldecode($uriPath);

        if (
         preg_match('#/(?:uploads|wp-content/uploads)/woocommerce_uploads/#', $uriPath)
         && !str_starts_with($requestedPath, 'woocommerce_uploads/')
        )
        {
            $requestedPath = 'woocommerce_uploads/' . ltrim($requestedPath, '/');
        }

        return $requestedPath;
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

        $canonicalUploadDir = self::UPLOAD_DIR . 'new/';
        $datePath = date('Y/m/d') . '/';
        $datedUploadDir = $canonicalUploadDir . $datePath;
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
            $storedPath = FileLinkService::canonicalizeNativeFilePath($targetPath);
            if ($storedPath === null)
            {
                throw new \RuntimeException('Uploaded file could not be resolved inside the upload directory.');
            }

            $fileRecord = File::create([
                'owner' => $userId,
                'name' => $sanitizedFilename,
                'type' => $extension,
                'size' => $fileSize,
                'path' => $storedPath,
                // get domain
                'host' => parse_url((string)(getenv('UPLOAD_URL') ?: ($_ENV['UPLOAD_URL'] ?? '')), PHP_URL_HOST) ?: 'unknown',
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

        $baseUrl = (string)(getenv('UPLOAD_URL') ?: ($_ENV['UPLOAD_URL'] ?? ''));
        $downloadUrl = FileLinkService::generateSecureFileLink($fileRecord, $baseUrl, null, $filename);

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
        $baseUrl = (string)(getenv('UPLOAD_URL') ?: ($_ENV['UPLOAD_URL'] ?? ''));
        $clientIp = RequestHelper::getClientIp($request) ?: null;
        $fileName = $result['original_name'] ?? $fileRecord->name;
        $result['download_url'] = FileLinkService::generateSecureFileLink($fileRecord, $baseUrl, $clientIp, $fileName);

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

        // New uploads share a collision-safe canonical namespace.
        $canonicalUploadDir = self::UPLOAD_DIR . 'new/';
        if (!is_dir($canonicalUploadDir))
        {
            mkdir($canonicalUploadDir, 0755, true);
        }

        // Create date-based subdirectory: YYYY/MM/DD
        $datePath = date('Y/m/d') . '/';
        $datedUploadDir = $canonicalUploadDir . $datePath;
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
            $storedPath = FileLinkService::canonicalizeNativeFilePath($targetPath);
            if ($storedPath === null)
            {
                throw new \RuntimeException('Uploaded file could not be resolved inside the upload directory.');
            }

            $fileRecord = File::create([
                'owner' => $user->id,
                'name' => $sanitizedFilename,
                'type' => $fileExtension,
                'size' => $fileSize,
                'path' => $storedPath,
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
                'download_url' => FileLinkService::generateSecureFileLink(
                    $fileRecord,
                    (string)(getenv('UPLOAD_URL') ?: ($_ENV['UPLOAD_URL'] ?? '')),
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
                $baseUrl = (string)(getenv('UPLOAD_URL') ?: ($_ENV['UPLOAD_URL'] ?? ''));
                $userIp = RequestHelper::getClientIp($request) ?: null;
                return [
                    'id' => $file->id,
                    'filename' => $file->name,
                    'original_filename' => $file->name, // Since we don't store original separately
                    'file_size' => $file->size,
                    'file_size_formatted' => FormatHelper::formatBytes($file->size),
                    'mime_type' => $file->type,
                    'file_path' => $file->path,
                    'download_url' => FileLinkService::generateSecureFileLink($file, $baseUrl, $userIp, $file->name),
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

    /** Log a download and hand the validated file off to nginx. */
    private function serveClassifiedFile(
        Request $request,
        Response $response,
        File $file,
        string $internalUri,
        ?string $downloadName = null
    ): Response {
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
            // Logging must not prevent an otherwise valid download.
        }

        $response = $response->withHeader('X-Accel-Redirect', $internalUri);
        $response = $response->withHeader('X-Accel-Buffering', 'no');
        $response = $response->withHeader('Content-Length', '0');
        $response = $response->withHeader(
            'Content-Disposition',
            $this->buildContentDisposition($downloadName ?: $file->name)
        );
        $response = $response->withHeader('Content-Type', 'application/octet-stream');
        $response->getBody()->write('');
        return $response;
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
            return $this->renderErrorPage(
                $response,
                400,
                'لینک دانلود ناقص است',
                'برای دانلود فایل، لینک کامل با پارامترهای امنیتی لازم است. لطفاً وارد حساب خود در تاپ جی اس ام شوید و دوباره روی دانلود کلیک کنید.',
                'اگر از صفحهٔ سایت به اینجا آمده‌اید، لینک ممکن است نادرست کپی شده باشد.'
            );
        }

        $path = '/files/serve/' . $fileId;
        $userIp = RequestHelper::getClientIp($request);

        if (!SecureLinkService::verifySecureLink($path, $providedMd5, $expires, $userIp))
        {
            return $this->renderErrorPage(
                $response,
                403,
                'لینک دانلود منقضی یا نامعتبر است',
                'این لینک دیگر قابل استفاده نیست. لینک‌های دانلود پس از مدتی منقضی می‌شوند.',
                'لطفاً دوباره از صفحهٔ دانلود ها، لینک دانلود جدید بگیرید.'
            );
        }

        // Find file by ID
        $file = File::find($fileId);
        if (!$file)
        {
            return $this->renderErrorPage(
                $response,
                404,
                'فایل یافت نشد',
                'فایلی با این شناسه در سیستم وجود ندارد. ممکن است فایل حذف شده یا شناسه اشتباه باشد.',
                'لطفا با پشتیبانی تماس بگیرید'
            );
        }

        // Check if file exists on disk
        if (!is_file((string) $file->path))
        {
            return $this->renderErrorPage(
                $response,
                404,
                'فایل روی دیسک یافت نشد',
                'رکورد فایل موجود است اما فایل فیزیکی روی سرور پیدا نشد. احتمالاً فایل حذف یا جابجا شده است.',
                'لطفا با پشتیبانی تماس بگیرید'
            );
        }

        $classification = FileLinkService::classifyFile($file);
        if ($classification === null)
        {
            return $this->renderErrorPage(
                $response,
                403,
                'مسیر فایل معتبر نیست',
                'مسیر فایل خارج از محدودهٔ مجاز است. تلاش برای دسترسی غیرمجاز شناسایی شد.',
                'لطفا با پشتیبانی تماس بگیرید'
            );
        }

        return $this->serveClassifiedFile(
            $request,
            $response,
            $file,
            $classification['internal_uri']
        );
    }

    /**
     * Submit a delete request for a file owned by the authenticated user.
     * POST /files/{id}/delete-request  body: { reason?: string }
     */
    public function requestFileDeletion(Request $request, Response $response, array $args): Response
    {
        $fileId = (int)($args['id'] ?? 0);
        if ($fileId <= 0)
        {
            return $this->json($response, ['error' => 'File ID is required'], 400);
        }

        $user = User::find($_SESSION['user_id'] ?? 0);
        if (!$user)
        {
            return $this->json($response, ['error' => 'User not found'], 404);
        }

        $file = File::find($fileId);
        if (!$file)
        {
            return $this->json($response, ['error' => 'File not found'], 404);
        }

        if ((int)$file->owner !== (int)$user->id)
        {
            return $this->json($response, ['error' => 'You do not own this file'], 403);
        }

        // Prevent duplicate pending requests for the same file
        $existing = DeleteRequest::where('file_id', $fileId)
            ->where('status', 'pending')
            ->first();
        if ($existing)
        {
            return $this->json($response, ['error' => 'A delete request for this file is already pending'], 409);
        }

        $body = $request->getParsedBody();
        $reason = isset($body['reason']) ? trim((string)$body['reason']) : null;
        if ($reason === '')
        {
            $reason = null;
        }

        $deleteRequest = DeleteRequest::create([
            'file_id' => $fileId,
            'user_id' => $user->id,
            'reason' => $reason,
            'status' => 'pending',
        ]);

        return $this->json($response, [
            'message' => 'Delete request submitted successfully. Awaiting admin approval.',
            'request' => [
                'id' => $deleteRequest->id,
                'file_id' => $deleteRequest->file_id,
                'status' => $deleteRequest->status,
                'created_at' => $deleteRequest->created_at,
            ],
        ], 201);
    }

    /**
     * Get delete requests submitted by the authenticated user.
     * GET /files/delete-requests
     */
    public function getUserDeleteRequests(Request $request, Response $response): Response
    {
        $user = User::find($_SESSION['user_id'] ?? 0);
        if (!$user)
        {
            return $this->json($response, ['error' => 'User not found'], 404);
        }

        $requests = DeleteRequest::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($dr)
            {
                $file = File::find($dr->file_id);
                return [
                    'id' => $dr->id,
                    'file_id' => $dr->file_id,
                    'filename' => $file ? $file->name : 'حذف‌شده',
                    'reason' => $dr->reason,
                    'status' => $dr->status,
                    'admin_note' => $dr->admin_note,
                    'created_at' => $dr->created_at,
                    'updated_at' => $dr->updated_at,
                ];
            });

        return $this->json($response, ['requests' => $requests]);
    }

    /**
     * Serve signed native and WordPress storage-relative paths.
     * WordPress files are registered on first download; native files must already be tracked.
     * URL patterns:
     *   /wp-content/uploads/2026/02/filename.rar
     *   /uploads/2026/02/filename.rar
     *   /uploads/woocommerce_uploads/2026/02/filename.rar
     * Requires query params md5 and expires (same as file serve); IP is checked when present via the same optional-IP verification.
     */
    public function serveWpContentFile(Request $request, Response $response, array $args): Response
    {
        $requestedPath = self::normalizeWpUploadPath($args['path'] ?? '', $request->getUri()->getPath());
        if ($requestedPath === '')
        {
            return $this->renderErrorPage(
                $response,
                400,
                'مسیر فایل مشخص نشده',
                'آدرس فایل برای دانلود ارسال نشده است. لطفاً از لینک اصلی سایت استفاده کنید.',
                'اگر از جای دیگری این لینک را کپی کرده‌اید، لینک ناقص است.'
            );
        }

        // Reject null bytes (directory injection / legacy PHP path issues)
        if (strpos($requestedPath, "\0") !== false)
        {
            return $this->renderErrorPage(
                $response,
                400,
                'مسیر فایل نامعتبر است',
                'آدرس درخواستی شامل کاراکترهای غیرمجاز است و قابل پردازش نیست.',
                'لطفاً از لینک صحیح صفحهٔ سایت استفاده کنید.'
            );
        }

        $params = $request->getQueryParams();
        $providedMd5 = $params['md5'] ?? null;
        $expires = $params['expires'] ?? null;
        if ($providedMd5 === null || $providedMd5 === '' || $expires === null || $expires === '')
        {
            return $this->renderErrorPage(
                $response,
                400,
                'لینک دانلود ناقص است',
                'برای دانلود فایل، پارامترهای امنیتی (md5 و expires) لازم است. لطفاً از صفحهٔ اصلی سایت لینک بگیرید.',
                'وارد اکانت خود در تاپ جی اس ام شوید و دوباره روی دانلود کلیک کنید.'
            );
        }

        $pathForHash = $request->getUri()->getPath();
        $userIp = RequestHelper::getClientIp($request);
        if (!SecureLinkService::verifySecureLink($pathForHash, $providedMd5, $expires, $userIp))
        {
            return $this->renderErrorPage(
                $response,
                403,
                'لینک دانلود منقضی یا نامعتبر است',
                'این لینک امنیتی دیگر معتبر نیست یا منقضی شده. لینک‌های محافظت‌شده پس از مدتی غیرفعال می‌شوند.',
                'وارد اکانت خود در تاپ جی اس ام شوید و دوباره روی دانلود کلیک کنید.'
            );
        }

        $host = $request->getHeaderLine('Host');
        $baseUrl = (string)(getenv('UPLOAD_URL') ?: ($_ENV['UPLOAD_URL'] ?? ''));
        $uriPath = $request->getUri()->getPath();
        $isNativePath = str_starts_with($uriPath, '/uploads/')
            && FileLinkService::isNativeUploadHost($host, $baseUrl)
            && preg_match('#^(?:new|[1-9][0-9]*)/[0-9]{4}/[0-9]{2}/[0-9]{2}/[^/]+$#D', $requestedPath);

        if ($isNativePath)
        {
            $target = FileLinkService::resolveNativePath($requestedPath);
            if ($target === null)
            {
                return $this->renderErrorPage(
                    $response,
                    404,
                    'فایل یافت نشد',
                    'فایل درخواستی در مسیر آپلودها وجود ندارد. ممکن است فایل حذف شده یا آدرس اشتباه باشد.',
                    'لطفا با پشتیبانی تماس بگیرید'
                );
            }

            // Native path URLs never create records implicitly. This prevents a
            // signed path from exposing an untracked file in the upload tree.
            $file = FileLinkService::findNativeFile($target);
            if (!$file)
            {
                return $this->renderErrorPage(
                    $response,
                    404,
                    'فایل یافت نشد',
                    'برای فایل درخواستی رکورد معتبری در سیستم وجود ندارد.',
                    'لطفا با پشتیبانی تماس بگیرید'
                );
            }

            return $this->serveClassifiedFile(
                $request,
                $response,
                $file,
                $target['internal_uri']
            );
        }

        $target = FileLinkService::resolveWpPath($requestedPath, $host);
        if ($target === null)
        {
            return $this->renderErrorPage(
                $response,
                404,
                'فایل یافت نشد',
                'فایل درخواستی در مسیر آپلودهای این دامنه وجود ندارد یا مسیر آن معتبر نیست.',
                'لطفا با پشتیبانی تماس بگیرید'
            );
        }

        $resolved = $target['resolved_path'];
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
                'host' => $target['domain'] ?? (FileLinkService::normalizeHost($host) ?: null),
            ]);
        }

        return $this->serveClassifiedFile(
            $request,
            $response,
            $file,
            $target['internal_uri'],
            $name
        );
    }
}
