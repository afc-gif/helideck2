<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PlatformOrganizationController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('view-platform-roles');

        $organizations = Organization::query()
            ->where('status', 'pending')
            ->with(['memberships' => fn ($query) => $query->where('status', 'pending')->whereHas('role', fn ($roleQuery) => $roleQuery->where('slug', 'organization_admin'))->with('user:id,name,email')])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Organization $organization): array => [
                'public_id' => $organization->public_id,
                'name' => $organization->name,
                'requested_at' => $organization->created_at?->toISOString(),
                'contact' => $organization->memberships->first()?->user === null ? null : [
                    'name' => $organization->memberships->first()->user->name,
                    'email' => $organization->memberships->first()->user->email,
                ],
            ]);

        return response()->json(['data' => $organizations]);
    }

    public function approve(string $publicId): JsonResponse
    {
        Gate::authorize('view-platform-roles');

        $organization = DB::transaction(function () use ($publicId): Organization {
            $organization = Organization::query()
                ->where('public_id', $publicId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            $ownerMembership = $organization->memberships()
                ->where('status', 'pending')
                ->whereHas('role', fn ($query) => $query->where('slug', 'organization_admin'))
                ->firstOrFail();

            $organization->update(['status' => 'active']);
            $ownerMembership->update(['status' => 'active']);

            return $organization;
        });

        return response()->json([
            'data' => [
                'public_id' => $organization->public_id,
                'name' => $organization->name,
                'status' => $organization->status,
            ],
            'message' => 'Organization and its first administrator have been approved.',
        ]);
    }
}
