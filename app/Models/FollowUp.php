<?php

namespace App\Models;

use App\Enums\FollowUpStatus;
use App\Enums\FollowUpType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class FollowUp extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_type', 'subject_id', 'contact_id', 'assigned_to',
        'type', 'due_date', 'due_time', 'note', 'status', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => FollowUpType::class,
            'status' => FollowUpStatus::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
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

    public function isOverdue(): bool
    {
        return $this->status === FollowUpStatus::Pending
            && $this->due_date->isPast();
    }
}
