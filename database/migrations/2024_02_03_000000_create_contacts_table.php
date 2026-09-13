<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner: a Contact belongs to exactly one Lead or one Client.
            // contactable_type stores 'lead' or 'client' (App\Enums\ContactableType),
            // not a class-name morph map, to keep the DB decoupled from PHP namespaces.
            $table->string('contactable_type', 20);
            $table->unsignedBigInteger('contactable_id');

            $table->string('name');
            $table->string('job_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('whatsapp')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_primary')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['contactable_type', 'contactable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
