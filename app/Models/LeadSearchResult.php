<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class LeadSearchResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_search_id', 'company_name', 'website', 'country', 'city', 'address',
        'industry', 'description', 'contact_name', 'contact_title', 'email', 'phone',
        'linkedin_url', 'import_indicator', 'export_indicator', 'shipping_relevance',
        'potential_shipping_need', 'source_url', 'source_title', 'source_snippet',
        'confidence_score', 'lead_score', 'score_breakdown',
        'company_domain', 'normalized_company_name', 'is_duplicate', 'duplicate_of_lead_id',
        'review_status', 'saved_as_lead_id',
    ];

    protected function casts(): array
    {
        return [
            'import_indicator' => 'boolean',
            'export_indicator' => 'boolean',
            'is_duplicate' => 'boolean',
            'score_breakdown' => 'array',
        ];
    }

    public function leadSearch(): BelongsTo
    {
        return $this->belongsTo(LeadSearch::class);
    }

    public function duplicateOfLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'duplicate_of_lead_id');
    }

    public function savedAsLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'saved_as_lead_id');
    }
}
