<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // Company info
            $table->string('company_name');
            $table->string('website')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('industry')->nullable();
            $table->text('description')->nullable();

            // Primary contact snapshot (full contact records live in `contacts`)
            $table->string('contact_name')->nullable();
            $table->string('contact_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('linkedin_url')->nullable();

            // Provenance - where this lead came from
            $table->string('source')->nullable(); // e.g. "ai_web_search", "manual", "referral"
            $table->string('source_url')->nullable();
            $table->foreignId('lead_search_result_id')->nullable()
                ->constrained('lead_search_results')->nullOnDelete();

            // Scoring
            $table->unsignedTinyInteger('lead_score')->nullable();
            $table->string('shipping_relevance')->nullable();
            $table->text('potential_need')->nullable();

            // Pipeline
            $table->string('status', 32)->default('new')->index();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();

            // Conversion. client_id FK is added later (2024_02_02_500000) once `clients` exists,
            // since Lead <-> Client is a mutual reference (Lead.client_id / Client.source_lead_id).
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('client_id')->nullable();

            // Deduplication helpers
            $table->string('company_domain')->nullable()->index();
            $table->string('normalized_company_name')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
