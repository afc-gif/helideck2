<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'scope' => $this->scope,
            'permissions' => $this->whenLoaded('permissions', fn (): array => $this->permissions->pluck('slug')->values()->all()),
        ];
    }
}
