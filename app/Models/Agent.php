<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Agent = an EXTERNAL freight/shipping partner Pyramidth works with.
 * NOT a Pyramidth employee/User - do not conflate with the users table.
 */
class Agent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_name', 'country', 'city', 'address', 'website',
        'contact_person', 'email', 'phone', 'services', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'services' => 'array',
        ];
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
