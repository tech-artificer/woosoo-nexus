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
            'avatar_url' => $this->avatar_url ?? null,
            'status' => $this->status ?? 'active',
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'role_names' => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')->toArray(), []),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'branches' => $this->whenLoaded('branches', fn() => $this->branches->toArray(), []),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}
