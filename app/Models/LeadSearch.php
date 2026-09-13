<?php

namespace App\Models;

use App\Enums\LeadSearchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class LeadSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'query', 'filters', 'status', 'current_step',
        'results_count', 'error_message', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'status' => LeadSearchStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(LeadSearchResult::class);
    }
}
