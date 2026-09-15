<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ScheduledEmail extends Model
{
    protected $fillable = ['user_id', 'to_email', 'cc_emails', 'subject', 'body', 'scheduled_at', 'timezone', 'status', 'sent_at', 'error_message'];

    protected function casts(): array
    {
        return ['cc_emails' => 'array', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
