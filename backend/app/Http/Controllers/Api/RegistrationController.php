<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegistrationRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    public function organizations(): JsonResponse
    {
        $organizations = Organization::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['public_id', 'name']);

        return response()->json(['data' => $organizations]);
    }

    public function store(StoreRegistrationRequest $request): JsonResponse
    {
        $registration = $request->validated();
        $accountType = $registration['account_type'];
        $organization = $accountType === 'field_staff'
            ? Organization::query()->where('public_id', $registration['organization_public_id'])->where('status', 'active')->firstOrFail()
            : null;
        $roleSlug = $accountType === 'organization' ? 'organization_admin' : 'surveyor';
        $role = Role::query()->where('scope', 'organization')->where('slug', $roleSlug)->firstOrFail();

        DB::transaction(function () use ($registration, $organization, $role): void {
            $user = User::query()->create([
                'name' => $registration['name'],
                'email' => $registration['email'],
                'password' => $registration['password'],
            ]);

            $requestedOrganization = $organization ?? Organization::query()->create([
                'name' => $registration['organization_name'],
                'slug' => substr(Str::slug($registration['organization_name']) ?: 'organization', 0, 218).'-'.Str::lower((string) Str::uuid()),
                'status' => 'pending',
            ]);

            $requestedOrganization->memberships()->create([
                'user_id' => $user->getKey(),
                'role_id' => $role->getKey(),
                'status' => 'pending',
            ]);
        });

        return response()->json([
            'message' => $accountType === 'organization'
                ? 'Your organization application has been sent to ARTSCI for review.'
                : 'Your account request has been sent. You can sign in after an organization administrator approves it.',
        ], 201);
    }
}
