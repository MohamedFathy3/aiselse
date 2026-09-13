<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            $table->string('company_name');
            $table->string('website')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('industry')->nullable();
            $table->text('description')->nullable();

            $table->string('status', 20)->default('active')->index(); // active|inactive|on_hold

            $table->foreignId('user_id')->comment('Sales owner')->constrained('users')->cascadeOnDelete();

            // A Client always originates from a converted Lead (see section 13: no auto-conversion,
            // but every Client must be traceable back to the Lead that produced it).
            $table->foreignId('source_lead_id')->nullable()->constrained('leads')->nullOnDelete();

            // Deduplication helpers, mirrors `leads` so we can warn before creating a duplicate Client.
            $table->string('company_domain')->nullable()->index();
            $table->string('normalized_company_name')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
