<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Polymorphic subject: which Lead or Client this activity is about.
            $table->string('subject_type', 20); // 'lead' | 'client'
            $table->unsignedBigInteger('subject_id');

            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();

            $table->string('type', 20); // call|email|meeting|whatsapp|note|follow_up|status_change
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // e.g. {"from_status": "new", "to_status": "contacted"}

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_activities');
    }
};
