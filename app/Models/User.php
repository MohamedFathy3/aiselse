<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        // Google tokens must never be serialized to the frontend, under any circumstance.
        'google_access_token',
        'google_refresh_token',
        'linkedin_access_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'google_token_expires_at' => 'datetime',
            'google_connected_at' => 'datetime',
            'linkedin_access_token' => 'encrypted',
            'linkedin_connected_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSales(): bool
    {
        return $this->role === UserRole::Sales;
    }

    public function hasConnectedGoogle(): bool
    {
        return filled($this->google_refresh_token);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'salesman_id');
    }

    public function salesActivities(): HasMany
    {
        return $this->hasMany(SalesActivity::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'assigned_to');
    }

    public function leadSearches(): HasMany
    {
        return $this->hasMany(LeadSearch::class);
    }
}
