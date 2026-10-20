<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveOrganizationMemberRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrganizationMemberController extends Controller
{
    public function index(Request $request, string $publicId): JsonResponse
    {
        $organization = Organization::query()
            ->visibleTo($request->user())
            ->where('public_id', $publicId)
            ->firstOrFail();

        Gate::authorize('manageMembers', $organization);

        $members = $organization->memberships()
            ->with(['user:id,name,email', 'role:id,slug,name'])
            ->whereIn('status', ['pending', 'active'])
            ->orderBy('id')
            ->get()
            ->map(fn ($membership): array => [
                'id' => $membership->user->getKey(),
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->slug,
                'status' => $membership->status,
            ]);

        return response()->json(['data' => $members]);
    }

    public function approve(ApproveOrganizationMemberRequest $request, string $publicId, int $userId): JsonResponse
    {
        $organization = Organization::query()
            ->visibleTo($request->user())
            ->where('public_id', $publicId)
            ->firstOrFail();

        Gate::authorize('manageMembers', $organization);

        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->firstOrFail();
        $role = Role::query()
            ->where('scope', 'organization')
            ->where('slug', $request->validated('role'))
            ->firstOrFail();

        $membership->update(['role_id' => $role->getKey(), 'status' => 'active']);

        return response()->json([
            'data' => [
                'id' => $membership->user->getKey(),
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $role->slug,
                'status' => 'active',
            ],
            'message' => 'Account approved.',
        ]);
    }
}
