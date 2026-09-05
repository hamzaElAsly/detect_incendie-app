<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $factory = $this->relationLoaded('factories') ? $this->factories->first() : null;

        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'email_verified_at' => $this->email_verified_at, 'is_active' => $this->is_active, 'is_super_admin' => $this->when($this->is_super_admin, true), 'last_login_at' => $this->last_login_at, 'factory' => $factory ? new FactoryResource($factory) : null];
    }
}
