<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agent = an EXTERNAL freight/shipping partner. Not a Pyramidth employee/user.
        Schema::create('agents', function (Blueprint $table) {
            $table->id();

            $table->string('company_name');
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('website')->nullable();

            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->json('services')->nullable(); // e.g. ["ocean", "air", "customs_clearance"]
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active')->index(); // active|inactive

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
