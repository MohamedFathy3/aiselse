<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            // Only ever a boolean flag - the tokens themselves are hidden on the model
            // and never reach the frontend under any circumstance.
            'google_connected' => $this->hasConnectedGoogle(),
            'google_account_email' => $this->when($this->hasConnectedGoogle(), $this->google_account_email),
            'created_at' => $this->created_at,
        ];
    }
}
