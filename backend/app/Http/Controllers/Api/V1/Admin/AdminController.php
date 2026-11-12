<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Models\Role;
use App\Services\Admin\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Admin::class);

        $admins = Admin::with('roles')
            ->orderBy('created_at', 'desc')
            ->paginate((int) min(50, max(1, (int) $request->input('per_page', 15))));

        return response()->json([
            'data' => AdminResource::collection($admins),
            'meta' => [
                'current_page' => $admins->currentPage(),
                'last_page' => $admins->lastPage(),
                'per_page' => $admins->perPage(),
                'total' => $admins->total(),
            ],
        ]);
    }

    public function store(CreateAdminRequest $request): JsonResponse
    {
        $admin = Admin::create([
            'id' => (string) Str::uuid(),
            'name' => (string) $request->input('name'),
            'email' => strtolower((string) $request->input('email')),
            'password' => Hash::make((string) $request->input('password')),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $roleIds = Role::whereIn('name', (array) $request->input('roles'))->pluck('id');
        $admin->roles()->sync($roleIds);

        $this->auditService->log(
            action: 'admin.created',
            target: $admin,
            metadata: ['roles' => $request->input('roles')],
            request: $request
        );

        return response()->json([
            'message' => 'Administrator created successfully.',
            'admin' => new AdminResource($admin->fresh()->load('roles')),
        ], Response::HTTP_CREATED);
    }

    public function show(Admin $admin): JsonResponse
    {
        $this->authorize('view', $admin);

        return response()->json([
            'admin' => new AdminResource($admin->load('roles.permissions')),
        ]);
    }

    public function update(UpdateAdminRequest $request, Admin $admin): JsonResponse
    {
        $updates = [];
        if ($request->has('name')) {
            $updates['name'] = (string) $request->input('name');
        }

        if (! empty($updates)) {
            $admin->update($updates);
        }

        if ($request->has('roles')) {
            $this->authorize('roles.manage');
            $roleIds = Role::whereIn('name', (array) $request->input('roles'))->pluck('id');
            $admin->roles()->sync($roleIds);
        }

        $this->auditService->log(
            action: 'admin.updated',
            target: $admin,
            metadata: $updates,
            request: $request
        );

        return response()->json([
            'message' => 'Administrator updated successfully.',
            'admin' => new AdminResource($admin->fresh()->load('roles')),
        ]);
    }

    public function toggleActive(Request $request, Admin $admin): JsonResponse
    {
        $this->authorize('disable', $admin);

        $newState = ! $admin->is_active;
        $admin->update(['is_active' => $newState]);

        $this->auditService->log(
            action: $newState ? 'admin.activated' : 'admin.deactivated',
            target: $admin,
            metadata: ['is_active' => $newState],
            request: $request
        );

        return response()->json([
            'message' => $newState ? 'Administrator activated.' : 'Administrator deactivated.',
            'admin' => new AdminResource($admin->fresh()->load('roles')),
        ]);
    }
}
