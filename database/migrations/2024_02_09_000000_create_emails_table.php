<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately a metadata cache, not a mail store: Gmail remains the source of
        // truth for message bodies. We persist just enough to associate emails with CRM
        // records and render a timeline without re-hitting the Gmail API constantly.
        Schema::create('emails', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->comment('Which Sales user\'s mailbox this came from')
                ->constrained('users')->cascadeOnDelete();

            $table->string('subject_type', 20)->nullable(); // 'lead' | 'client'
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();

            $table->string('gmail_message_id')->unique();
            $table->string('gmail_thread_id')->index();

            $table->string('direction', 10); // inbound|outbound
            $table->string('from_email');
            $table->json('to_emails')->nullable();
            $table->json('cc_emails')->nullable();
            $table->string('subject')->nullable();
            $table->text('snippet')->nullable();
            $table->json('attachments_metadata')->nullable();

            $table->boolean('is_ai_drafted')->default(false);
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
