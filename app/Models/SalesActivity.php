<?php

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class SalesActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'subject_type', 'subject_id', 'contact_id',
        'type', 'subject', 'description', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function subject(): Lead|Client|null
    {
        return match ($this->subject_type) {
            'lead' => Lead::find($this->subject_id),
            'client' => Client::find($this->subject_id),
            default => null,
        };
    }
}
