<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\File;
use App\Models\DownloadLog;
use App\Models\DeleteRequest;
use App\Service\FileLinkService;
use App\Service\SslService;
use App\Util\FormatHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminController extends Controller
{
    // ==================== FILE ENDPOINTS ====================

    /**
     * Get all files with pagination, search, and filters
     */
    public function getAllFiles(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 30)));
        $search = $params['search'] ?? null;
        $ownerName = $params['owner_name'] ?? null;
        $host = $params['host'] ?? null;
        $type = $params['type'] ?? null;
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;
        $downloadFromDate = $params['download_from_date'] ?? null;
        $downloadToDate = $params['download_to_date'] ?? null;
        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortDir = $params['sort_dir'] ?? 'desc';

        // Validate sort parameters
        $allowedSortBy = ['created_at', 'size', 'downloads'];
        if (!in_array($sortBy, $allowedSortBy)) {
            $sortBy = 'created_at';
        }
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        $offset = ($page - 1) * $limit;

        // Build download count subquery with optional date range
        $downloadCountSql = '(SELECT COUNT(*) FROM download_log WHERE download_log.file_id = file.id';
        $bindings = [];
        if ($downloadFromDate !== null && trim($downloadFromDate) !== '') {
            $downloadCountSql .= ' AND download_log.created_at >= ?';
            $bindings[] = $downloadFromDate . ' 00:00:00';
        }
        if ($downloadToDate !== null && trim($downloadToDate) !== '') {
            $downloadCountSql .= ' AND download_log.created_at <= ?';
            $bindings[] = $downloadToDate . ' 23:59:59';
        }
        $downloadCountSql .= ') as download_count';

        $query = File::query()
            ->selectRaw('file.*, ' . $downloadCountSql, $bindings);

        // Filter by owner username
        if ($ownerName !== null && trim($ownerName) !== '') {
            $ownerIds = User::where('username', 'LIKE', '%' . $ownerName . '%')->pluck('id')->toArray();
            $query->whereIn('owner', $ownerIds);
        }

        // Filter by host
        if ($host !== null && trim($host) !== '') {
            $query->where('host', 'LIKE', '%' . trim($host) . '%');
        }

        // Filter by file type
        if ($type !== null && trim($type) !== '') {
            $query->where('type', $type);
        }

        // Search by filename
        if ($search !== null && trim($search) !== '') {
            $query->where('name', 'LIKE', '%' . $search . '%');
        }

        // Date range filters
        if ($fromDate !== null && trim($fromDate) !== '') {
            $query->where('created_at', '>=', $fromDate . ' 00:00:00');
        }
        if ($toDate !== null && trim($toDate) !== '') {
            $query->where('created_at', '<=', $toDate . ' 23:59:59');
        }

        $totalFiles = $query->count();

        // Apply sorting
        $sortColumn = $sortBy === 'downloads' ? 'download_count' : $sortBy;
        $files = $query->orderBy($sortColumn, $sortDir)
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($file) {
                $owner = User::find($file->owner);
                $baseUrl = (string) (getenv('UPLOAD_URL') ?: '');
                return [
                    'id' => $file->id,
                    'filename' => $file->name,
                    'type' => $file->type,
                    'size' => $file->size,
                    'size_formatted' => FormatHelper::formatBytes($file->size),
                    'owner_id' => $file->owner,
                    'owner_name' => $owner ? $owner->username : 'Unknown',
                    'download_count' => (int) $file->download_count,
                    'download_url' => FileLinkService::generateUnsignedFileLink($file, $baseUrl),
                    'path' => $file->path,
                    'host' => $file->host ?? null,
                    'created_at' => $file->created_at,
                    'updated_at' => $file->updated_at,
                ];
            });

        $totalPages = $totalFiles > 0 ? ceil($totalFiles / $limit) : 1;

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
     * Delete a file by ID (removes from disk and database)
     */
    public function deleteFile(Request $request, Response $response, array $args): Response
    {
        $fileId = $args['id'] ?? null;

        if (!$fileId) {
            return $this->json($response, ['error' => 'File ID is required'], 400);
        }

        $file = File::find($fileId);
        if (!$file) {
            return $this->json($response, ['error' => 'File not found'], 404);
        }

        // Delete from disk
        if (file_exists($file->path)) {
            if (!unlink($file->path)) {
                return $this->json($response, ['error' => 'Failed to delete file from disk'], 500);
            }
        }

        $filename = $file->name;

        // Delete associated download logs first to avoid orphaned records
        DownloadLog::where('file_id', $file->id)->delete();

        // Delete file record from database
        $file->delete();

        return $this->json($response, [
            'message' => 'File "' . $filename . '" deleted successfully'
        ]);
    }

    // ==================== USER ENDPOINTS ====================

    /**
     * Get all users with pagination
     */
    public function getAllUsers(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 30)));
        $search = $params['search'] ?? null;

        $offset = ($page - 1) * $limit;

        $query = User::query();

        if ($search !== null && trim($search) !== '') {
            $query->where('username', 'LIKE', '%' . $search . '%');
        }

        $totalUsers = $query->count();

        $users = $query->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($user) {
                $fileCount = File::where('owner', $user->id)->count();
                $totalSize = File::where('owner', $user->id)->sum('size');
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'allowedFileTypes' => $user->allowedFileTypes,
                    'is_admin' => (bool) $user->is_admin,
                    'file_count' => $fileCount,
                    'total_size' => (int) $totalSize,
                    'total_size_formatted' => FormatHelper::formatBytes((int) $totalSize),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            });

        $totalPages = $totalUsers > 0 ? ceil($totalUsers / $limit) : 1;

        return $this->json($response, [
            'users' => $users,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_users' => $totalUsers,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
            ]
        ]);
    }

    /**
     * Create a new user (admin action)
     */
    public function createUser(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        $allowedFileTypes = $data['allowedFileTypes'] ?? 'jpg,png,pdf';
        $isAdmin = (bool) ($data['is_admin'] ?? false);

        if (empty($username) || empty($password)) {
            return $this->json($response, ['error' => 'Username and password are required'], 400);
        }

        if (strlen($username) < 3) {
            return $this->json($response, ['error' => 'Username must be at least 3 characters'], 400);
        }

        if (strlen($password) < 6) {
            return $this->json($response, ['error' => 'Password must be at least 6 characters'], 400);
        }

        if (User::where('username', $username)->exists()) {
            return $this->json($response, ['error' => 'Username already exists'], 400);
        }

        $user = User::create([
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'allowedFileTypes' => $allowedFileTypes,
            'is_admin' => $isAdmin,
        ]);

        return $this->json($response, [
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'allowedFileTypes' => $user->allowedFileTypes,
                'is_admin' => (bool) $user->is_admin,
                'created_at' => $user->created_at,
            ]
        ], 201);
    }

    /**
     * Update a user (admin action)
     */
    public function updateUser(Request $request, Response $response, array $args): Response
    {
        $userId = $args['id'] ?? null;

        if (!$userId) {
            return $this->json($response, ['error' => 'User ID is required'], 400);
        }

        $user = User::find($userId);
        if (!$user) {
            return $this->json($response, ['error' => 'User not found'], 404);
        }

        $data = $request->getParsedBody();

        // Update username if provided
        if (isset($data['username']) && $data['username'] !== $user->username) {
            $newUsername = $data['username'];
            if (strlen($newUsername) < 3) {
                return $this->json($response, ['error' => 'Username must be at least 3 characters'], 400);
            }
            if (User::where('username', $newUsername)->where('id', '!=', $user->id)->exists()) {
                return $this->json($response, ['error' => 'Username already exists'], 400);
            }
            $user->username = $newUsername;
        }

        // Update password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                return $this->json($response, ['error' => 'Password must be at least 6 characters'], 400);
            }
            $user->password = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        // Update allowedFileTypes if provided
        if (isset($data['allowedFileTypes'])) {
            $user->allowedFileTypes = $data['allowedFileTypes'];
        }

        // Update is_admin if provided
        if (isset($data['is_admin'])) {
            // Prevent admin from removing their own admin status
            if ((int) $userId === (int) $_SESSION['user_id'] && !$data['is_admin']) {
                return $this->json($response, ['error' => 'You cannot remove your own admin status'], 400);
            }
            $user->is_admin = (bool) $data['is_admin'];
        }

        $user->save();

        return $this->json($response, [
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'allowedFileTypes' => $user->allowedFileTypes,
                'is_admin' => (bool) $user->is_admin,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }

    /**
     * Delete a user (keeps files intact, nullifies ownership)
     */
    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        $userId = $args['id'] ?? null;

        if (!$userId) {
            return $this->json($response, ['error' => 'User ID is required'], 400);
        }

        // Prevent self-deletion
        if ((int) $userId === (int) $_SESSION['user_id']) {
            return $this->json($response, ['error' => 'You cannot delete your own account'], 400);
        }

        $user = User::find($userId);
        if (!$user) {
            return $this->json($response, ['error' => 'User not found'], 404);
        }

        $username = $user->username;
        $fileCount = File::where('owner', $user->id)->count();

        // Nullify ownership on files so they (and their download_logs) remain intact
        File::where('owner', $user->id)->update(['owner' => null]);

        // Delete user
        $user->delete();

        return $this->json($response, [
            'message' => 'User "' . $username . '" deleted successfully. Their ' . $fileCount . ' file(s) have been kept (owner set to none).',
            'orphaned_files' => $fileCount,
        ]);
    }

    // ==================== DELETE REQUEST ENDPOINTS ====================

    /**
     * Get all delete requests with optional status filter.
     * GET /admin/delete-requests[?status=pending|approved|rejected]
     */
    public function getDeleteRequests(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $status = $params['status'] ?? null;
        $page   = max(1, (int)($params['page'] ?? 1));
        $limit  = max(1, min(100, (int)($params['limit'] ?? 30)));
        $offset = ($page - 1) * $limit;

        $query = DeleteRequest::query();

        if ($status !== null && in_array($status, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $status);
        }

        $total = $query->count();

        $requests = $query->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($dr) {
                $file = File::find($dr->file_id);
                $user = User::find($dr->user_id);
                return [
                    'id'         => $dr->id,
                    'file_id'    => $dr->file_id,
                    'filename'   => $file ? $file->name : 'حذف‌شده',
                    'file_size'  => $file ? (int)$file->size : 0,
                    'file_size_formatted' => $file ? FormatHelper::formatBytes((int)$file->size) : '-',
                    'user_id'    => $dr->user_id,
                    'username'   => $user ? $user->username : 'حذف‌شده',
                    'reason'     => $dr->reason,
                    'status'     => $dr->status,
                    'admin_note' => $dr->admin_note,
                    'created_at' => $dr->created_at,
                    'updated_at' => $dr->updated_at,
                ];
            });

        $totalPages = $total > 0 ? ceil($total / $limit) : 1;

        return $this->json($response, [
            'requests'   => $requests,
            'pagination' => [
                'current_page'  => $page,
                'per_page'      => $limit,
                'total_requests'=> $total,
                'total_pages'   => $totalPages,
                'has_next'      => $page < $totalPages,
                'has_prev'      => $page > 1,
            ],
        ]);
    }

    /**
     * Approve a delete request — deletes the file and marks request approved.
     * POST /admin/delete-requests/{id}/approve  body: { admin_note?: string }
     */
    public function approveDeleteRequest(Request $request, Response $response, array $args): Response
    {
        $requestId = (int)($args['id'] ?? 0);
        if ($requestId <= 0) {
            return $this->json($response, ['error' => 'Request ID is required'], 400);
        }

        $dr = DeleteRequest::find($requestId);
        if (!$dr) {
            return $this->json($response, ['error' => 'Delete request not found'], 404);
        }

        if ($dr->status !== 'pending') {
            return $this->json($response, ['error' => 'This request has already been ' . $dr->status], 409);
        }

        $file = File::find($dr->file_id);
        if ($file) {
            if (file_exists($file->path)) {
                @unlink($file->path);
            }
            DownloadLog::where('file_id', $file->id)->delete();
            $file->delete();
        }

        $body = $request->getParsedBody();
        $adminNote = isset($body['admin_note']) ? trim((string)$body['admin_note']) : null;

        $dr->status     = 'approved';
        $dr->admin_note = $adminNote ?: null;
        $dr->save();

        return $this->json($response, [
            'message' => 'Delete request approved. File has been deleted.',
            'request_id' => $dr->id,
        ]);
    }

    /**
     * Reject a delete request — file is kept, request marked rejected.
     * POST /admin/delete-requests/{id}/reject  body: { admin_note?: string }
     */
    public function rejectDeleteRequest(Request $request, Response $response, array $args): Response
    {
        $requestId = (int)($args['id'] ?? 0);
        if ($requestId <= 0) {
            return $this->json($response, ['error' => 'Request ID is required'], 400);
        }

        $dr = DeleteRequest::find($requestId);
        if (!$dr) {
            return $this->json($response, ['error' => 'Delete request not found'], 404);
        }

        if ($dr->status !== 'pending') {
            return $this->json($response, ['error' => 'This request has already been ' . $dr->status], 409);
        }

        $body = $request->getParsedBody();
        $adminNote = isset($body['admin_note']) ? trim((string)$body['admin_note']) : null;

        $dr->status     = 'rejected';
        $dr->admin_note = $adminNote ?: null;
        $dr->save();

        return $this->json($response, [
            'message' => 'Delete request rejected. File has been kept.',
            'request_id' => $dr->id,
        ]);
    }

    // ==================== SSL ENDPOINTS ====================

    /**
     * Get SSL certificate status and expiry info.
     * GET /admin/ssl
     */
    public function getSslInfo(Request $request, Response $response): Response
    {
        try
        {
            $ssl = new SslService();
            $info = $ssl->getInfo();
            return $this->json($response, $info);
        }
        catch (\Throwable $e)
        {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload SSL certificate and private key (multipart: cert, key).
     * POST /admin/ssl
     */
    public function uploadSsl(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();

        if (empty($uploadedFiles['cert']) || empty($uploadedFiles['key']))
        {
            return $this->json($response, ['error' => 'Both certificate (cert) and private key (key) files are required'], 400);
        }

        $certFile = $uploadedFiles['cert'];
        $keyFile = $uploadedFiles['key'];

        if ($certFile->getError() !== UPLOAD_ERR_OK)
        {
            return $this->json($response, ['error' => 'Certificate upload error'], 400);
        }
        if ($keyFile->getError() !== UPLOAD_ERR_OK)
        {
            return $this->json($response, ['error' => 'Private key upload error'], 400);
        }

        $certStream = $certFile->getStream();
        $keyStream = $keyFile->getStream();
        if ($certStream->tell() > 0)
        {
            $certStream->rewind();
        }
        if ($keyStream->tell() > 0)
        {
            $keyStream->rewind();
        }
        $certContent = (string) $certStream->getContents();
        $keyContent = (string) $keyStream->getContents();

        try
        {
            $ssl = new SslService();
            $result = $ssl->saveCertificates($certContent, $keyContent);
            $info = $ssl->getInfo();
            return $this->json($response, array_merge($result, ['ssl' => $info]));
        }
        catch (\InvalidArgumentException $e)
        {
            return $this->json($response, ['error' => $e->getMessage()], 400);
        }
        catch (\Throwable $e)
        {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reload nginx in the proxy container after SSL changes.
     * POST /admin/ssl/reload
     */
    public function reloadSsl(Request $request, Response $response): Response
    {
        $ssl = new SslService();
        $reloaded = $ssl->reloadNginxProxy();

        if (!$reloaded)
        {
            return $this->json($response, [
                'error' => 'Unable to reload nginx. Restart the proxy container manually.',
                'reloaded' => false,
            ], 500);
        }

        return $this->json($response, [
            'message' => 'Nginx reloaded successfully',
            'reloaded' => true,
        ]);
    }

    // ==================== STATS ENDPOINTS ====================

    /**
     * Get global statistics (all users)
     */
    public function getStats(Request $request, Response $response): Response
    {
        // Global file stats
        $totalFiles = File::count();
        $totalSize = File::sum('size');

        // Files by type
        $filesByType = File::selectRaw('type, COUNT(*) as count, SUM(size) as total_size')
            ->groupBy('type')
            ->orderByDesc('count')
            ->get()
            ->map(fn($row) => [
                'type' => $row->type,
                'count' => (int) $row->count,
                'total_size' => (int) $row->total_size,
                'total_size_formatted' => FormatHelper::formatBytes((int) $row->total_size),
            ]);

        // User stats
        $totalUsers = User::count();
        $adminCount = User::where('is_admin', true)->count();

        // Top users by file count
        $topUsersByFiles = File::selectRaw('owner, COUNT(*) as file_count, SUM(size) as total_size')
            ->groupBy('owner')
            ->orderByDesc('file_count')
            ->take(10)
            ->get()
            ->map(function ($row) {
                $user = User::find($row->owner);
                return [
                    'user_id' => $row->owner,
                    'username' => $user ? $user->username : 'Unknown',
                    'file_count' => (int) $row->file_count,
                    'total_size' => (int) $row->total_size,
                    'total_size_formatted' => FormatHelper::formatBytes((int) $row->total_size),
                ];
            });

        // Recent uploads (last 7 days)
        $recentUploads = File::where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))->count();

        // Uploads per day (last 30 days)
        $uploadsPerDay = File::where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-30 days')))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(size) as total_size')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date,
                'count' => (int) $row->count,
                'total_size' => (int) $row->total_size,
                'total_size_formatted' => FormatHelper::formatBytes((int) $row->total_size),
            ]);

        return $this->json($response, [
            'files' => [
                'total_count' => $totalFiles,
                'total_size' => (int) $totalSize,
                'total_size_formatted' => FormatHelper::formatBytes((int) $totalSize),
                'by_type' => $filesByType,
                'uploads_last_7_days' => $recentUploads,
                'uploads_per_day' => $uploadsPerDay,
            ],
            'users' => [
                'total_count' => $totalUsers,
                'admin_count' => $adminCount,
                'top_by_files' => $topUsersByFiles,
            ],
        ]);
    }

}
