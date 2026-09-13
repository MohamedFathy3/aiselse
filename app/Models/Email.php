<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

/**
 * Metadata cache of Gmail messages associated with CRM records.
 * Gmail remains the source of truth for full message bodies.
 */
class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'subject_type', 'subject_id', 'contact_id',
        'gmail_message_id', 'gmail_thread_id', 'direction',
        'from_email', 'to_emails', 'cc_emails', 'subject', 'snippet',
        'attachments_metadata', 'is_ai_drafted', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'to_emails' => 'array',
            'cc_emails' => 'array',
            'attachments_metadata' => 'array',
            'is_ai_drafted' => 'boolean',
            'sent_at' => 'datetime',
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
}
