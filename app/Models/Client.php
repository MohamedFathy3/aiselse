<?php

namespace App\Models;

use App\Enums\ClientStatus;
use App\Enums\ContactableType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_name', 'website', 'country', 'city', 'address', 'industry', 'description',
        'status', 'user_id', 'source_lead_id',
        'company_domain', 'normalized_company_name',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'source_lead_id');
    }

    /** The Lead(s) this Client was converted from (normally exactly one via sourceLead). */
    public function convertedFromLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'client_id');
    }

    public function contacts()
    {
        return Contact::query()
            ->where('contactable_type', ContactableType::Client->value)
            ->where('contactable_id', $this->id);
    }

    public function activities()
    {
        return SalesActivity::query()
            ->where('subject_type', 'client')
            ->where('subject_id', $this->id)
            ->latest('occurred_at');
    }

    public function followUps()
    {
        return FollowUp::query()
            ->where('subject_type', 'client')
            ->where('subject_id', $this->id);
    }

    public function emails()
    {
        return Email::query()
            ->where('subject_type', 'client')
            ->where('subject_id', $this->id);
    }

    public function calendarEvents()
    {
        return CalendarEvent::query()
            ->where('subject_type', 'client')
            ->where('subject_id', $this->id);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** Distinct Agents used across this client's shipment history (section 33). */
    public function agents()
    {
        return Agent::query()
            ->whereIn('id', $this->shipments()->whereNotNull('agent_id')->pluck('agent_id'));
    }
}
