<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_search_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_search_id')->constrained('lead_searches')->cascadeOnDelete();

            // Raw structured data extracted by AI/web-search, prior to Sales review.
            $table->string('company_name');
            $table->string('website')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('industry')->nullable();
            $table->text('description')->nullable();

            $table->string('contact_name')->nullable();
            $table->string('contact_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('linkedin_url')->nullable();

            $table->boolean('import_indicator')->default(false);
            $table->boolean('export_indicator')->default(false);
            $table->string('shipping_relevance')->nullable();
            $table->text('potential_shipping_need')->nullable();

            // Provenance - critical: never trust AI output without a source
            $table->string('source_url')->nullable();
            $table->string('source_title')->nullable();
            $table->text('source_snippet')->nullable();

            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->unsignedTinyInteger('lead_score')->nullable();
            $table->json('score_breakdown')->nullable(); // explainable scoring reasons

            $table->string('company_domain')->nullable()->index();
            $table->string('normalized_company_name')->nullable()->index();
            $table->boolean('is_duplicate')->default(false);
            // duplicate_of_lead_id / saved_as_lead_id FKs added in 2024_02_02_600000 once `leads` exists
            $table->unsignedBigInteger('duplicate_of_lead_id')->nullable();

            // Sales review state - a result only becomes a Lead when explicitly saved
            $table->string('review_status', 20)->default('pending')->index(); // pending|saved|ignored
            $table->unsignedBigInteger('saved_as_lead_id')->nullable();

            $table->timestamps();

            $table->index(['lead_search_id', 'review_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_search_results');
    }
};
