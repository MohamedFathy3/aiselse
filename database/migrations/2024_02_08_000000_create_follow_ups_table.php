<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();

            $table->string('subject_type', 20); // 'lead' | 'client'
            $table->unsignedBigInteger('subject_id');

            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();

            $table->string('type', 20); // call|email|meeting|whatsapp|general
            $table->date('due_date');
            $table->time('due_time')->nullable();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending|completed|cancelled|overdue
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['assigned_to', 'due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
