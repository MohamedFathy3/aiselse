<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Shipper extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'country', 'city', 'address', 'email', 'phone', 'client_id'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
