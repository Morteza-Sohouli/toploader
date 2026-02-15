<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\File;
use App\Models\DownloadLog;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminController extends Controller
{
    private const HASH_SALT = 'your-slsjfos8s8ohs8fhos;8dfs8fs8afoafsp;production';

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
            ->map(function ($file) use ($request) {
                $owner = User::find($file->owner);
                $baseUrl = (string) (getenv('UPLOAD_URL') ?: '');
                $userIp = $request->getServerParams()['REMOTE_ADDR'] ?? null;
                return [
                    'id' => $file->id,
                    'filename' => $file->name,
                    'type' => $file->type,
                    'size' => $file->size,
                    'size_formatted' => $this->formatBytes($file->size),
                    'owner_id' => $file->owner,
                    'owner_name' => $owner ? $owner->username : 'Unknown',
                    'download_count' => (int) $file->download_count,
                    'download_url' => FileController::generateSecureFileLink($file->id, $baseUrl, $userIp),
                    'path' => $file->path,
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
                    'total_size_formatted' => $this->formatBytes((int) $totalSize),
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
                'total_size_formatted' => $this->formatBytes((int) $row->total_size),
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
                    'total_size_formatted' => $this->formatBytes((int) $row->total_size),
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
                'total_size_formatted' => $this->formatBytes((int) $row->total_size),
            ]);

        return $this->json($response, [
            'files' => [
                'total_count' => $totalFiles,
                'total_size' => (int) $totalSize,
                'total_size_formatted' => $this->formatBytes((int) $totalSize),
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

    // ==================== HELPERS ====================

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

}
