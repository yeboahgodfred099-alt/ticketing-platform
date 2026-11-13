<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class AuditLogController extends Controller
{
    /**
     * List immutable administrative audit logs with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Admin $user */
        $user = $request->user();

        if (!$user->can('viewAny', AuditLog::class)) {
            return response()->json([
                'message' => 'Unauthorized: Audit log inspection restricted to authorized administrators.',
            ], Response::HTTP_FORBIDDEN);
        }

        $query = AuditLog::query()->with('admin');

        // Filters
        if ($action = $request->query('action')) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($adminId = $request->query('admin_id')) {
            $query->where('admin_id', $adminId);
        }

        if ($targetType = $request->query('target_type')) {
            $query->where('target_type', 'like', "%{$targetType}%");
        }

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', Carbon::parse($from));
        }

        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', Carbon::parse($to));
        }

        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));
        $logs = $query->orderByDesc('created_at')->paginate($perPage);

        return AuditLogResource::collection($logs)->response();
    }
}

