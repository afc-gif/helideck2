<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrganizationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Organization::class);

        return OrganizationResource::collection(
            Organization::query()->visibleTo($request->user())->orderBy('name')->get(),
        );
    }

    public function show(Request $request, string $publicId): OrganizationResource
    {
        $organization = Organization::query()
            ->visibleTo($request->user())
            ->where('public_id', $publicId)
            ->firstOrFail();

        Gate::authorize('view', $organization);

        return new OrganizationResource($organization);
    }
}
