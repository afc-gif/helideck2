<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_super_admin' => $this->isSuperAdmin(),
            'platform_roles' => $this->whenLoaded('platformRoles', fn (): array => $this->platformRoles->pluck('slug')->values()->all()),
            'memberships' => $this->whenLoaded('memberships', fn (): array => $this->memberships->map(fn ($membership): array => [
                'organization' => [
                    'public_id' => $membership->organization->public_id,
                    'name' => $membership->organization->name,
                    'slug' => $membership->organization->slug,
                ],
                'role' => $membership->role->slug,
                'permissions' => $membership->role->permissions->pluck('slug')->values()->all(),
            ])->values()->all()),
        ];
    }
}
