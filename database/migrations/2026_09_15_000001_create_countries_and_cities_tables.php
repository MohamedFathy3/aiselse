<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('countries', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('source_id')->unique(); $table->string('name'); $table->string('iso2', 2)->unique(); $table->string('iso3', 3)->nullable()->index(); $table->string('phonecode', 20)->nullable(); $table->timestamps(); });
        Schema::create('cities', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('source_id')->unique(); $table->foreignId('country_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->string('state_name')->nullable(); $table->decimal('latitude', 10, 7)->nullable(); $table->decimal('longitude', 10, 7)->nullable(); $table->timestamps(); $table->index(['country_id', 'name']); });
    }
    public function down(): void { Schema::dropIfExists('cities'); Schema::dropIfExists('countries'); }
};
