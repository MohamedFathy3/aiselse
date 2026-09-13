<?php

namespace App\Models;

use App\Enums\ContactableType;
use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_name', 'website', 'country', 'city', 'address', 'industry', 'description',
        'contact_name', 'contact_title', 'email', 'phone', 'linkedin_url',
        'source', 'source_url', 'lead_search_result_id',
        'lead_score', 'shipping_relevance', 'potential_need',
        'status', 'assigned_to',
        'company_domain', 'normalized_company_name',
    ];

    /**
     * Not mass-assignable from request input - only ever set by
     * LeadConversionService via explicit ->forceFill()/->update() calls,
     * so a Lead can never silently self-convert through a generic update.
     */
    protected $guarded_conversion_fields = ['client_id', 'converted_at', 'converted_by'];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'lead_score' => 'integer',
            'converted_at' => 'datetime',
        ];
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function leadSearchResult(): BelongsTo
    {
        return $this->belongsTo(LeadSearchResult::class);
    }

    public function contacts()
    {
        return Contact::query()
            ->where('contactable_type', ContactableType::Lead->value)
            ->where('contactable_id', $this->id);
    }

    public function activities()
    {
        return SalesActivity::query()
            ->where('subject_type', 'lead')
            ->where('subject_id', $this->id)
            ->latest('occurred_at');
    }

    public function followUps()
    {
        return FollowUp::query()
            ->where('subject_type', 'lead')
            ->where('subject_id', $this->id);
    }

    public function emails()
    {
        return Email::query()
            ->where('subject_type', 'lead')
            ->where('subject_id', $this->id);
    }

    public function calendarEvents()
    {
        return CalendarEvent::query()
            ->where('subject_type', 'lead')
            ->where('subject_id', $this->id);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
