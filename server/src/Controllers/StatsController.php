<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\File;
use App\Models\DownloadLog;
use App\Util\FormatHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StatsController extends Controller
{
    /**
     * Get statistics for the authenticated user
     */
    public function index(Request $request, Response $response): Response
    {
        $user = User::find($_SESSION['user_id']);
        if (!$user)
        {
            return $this->json($response, ['error' => 'User not found'], 404);
        }

        $userFileCount = File::where('owner', $user->id)->count();
        $userTotalSize = File::where('owner', $user->id)->sum('size');

        $userFilesByType = File::where('owner', $user->id)
            ->selectRaw('type, COUNT(*) as count, SUM(size) as total_size')
            ->groupBy('type')
            ->orderByDesc('count')
            ->get()
            ->map(fn($row) => [
                'type' => $row->type,
                'count' => (int)$row->count,
                'total_size' => (int)$row->total_size,
                'total_size_formatted' => FormatHelper::formatBytes((int)$row->total_size),
            ]);

        $userRecentUploads = File::where('owner', $user->id)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->count();

        // Download statistics
        $userFileIds = File::where('owner', $user->id)->pluck('id');

        $totalDownloads = DownloadLog::whereIn('file_id', $userFileIds)->count();

        $downloadsLast7Days = DownloadLog::whereIn('file_id', $userFileIds)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->count();

        return $this->json($response, [
            'file_count' => $userFileCount,
            'total_size' => (int)$userTotalSize,
            'total_size_formatted' => FormatHelper::formatBytes((int)$userTotalSize),
            'files_by_type' => $userFilesByType,
            'uploads_last_7_days' => $userRecentUploads,
            'total_downloads' => $totalDownloads,
            'downloads_last_7_days' => $downloadsLast7Days,
        ]);
    }

}
