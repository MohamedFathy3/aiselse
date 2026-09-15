<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scheduled_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('to_email');
            $table->json('cc_emails')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->timestamp('scheduled_at');
            $table->string('timezone', 64)->default('UTC');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'scheduled_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_emails');
    }
};
