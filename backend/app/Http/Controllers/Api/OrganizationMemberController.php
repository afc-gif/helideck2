<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
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
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(fn ($membership): array => [
                'id' => $membership->user->getKey(),
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->slug,
            ]);

        return response()->json(['data' => $members]);
    }
}
