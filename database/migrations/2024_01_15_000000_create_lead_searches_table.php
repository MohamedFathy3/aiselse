<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->text('query'); // the natural-language search the Sales user typed
            $table->json('filters')->nullable(); // country, city, industry, import/export, etc.

            $table->string('status', 20)->default('pending')->index(); // pending|processing|completed|failed
            $table->string('current_step')->nullable(); // "searching" | "collecting_sources" | "analyzing" | "filtering" | "deduplicating" | "completed"

            $table->unsignedInteger('results_count')->default(0);
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_searches');
    }
};
