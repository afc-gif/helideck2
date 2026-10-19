<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PlatformRoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('view-platform-roles');

        return RoleResource::collection(Role::query()->with('permissions')->orderBy('scope')->orderBy('name')->get());
    }
}
