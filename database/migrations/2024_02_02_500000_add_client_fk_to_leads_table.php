<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });

        Schema::table('lead_search_results', function (Blueprint $table) {
            $table->foreign('duplicate_of_lead_id')->references('id')->on('leads')->nullOnDelete();
            $table->foreign('saved_as_lead_id')->references('id')->on('leads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lead_search_results', function (Blueprint $table) {
            $table->dropForeign(['duplicate_of_lead_id']);
            $table->dropForeign(['saved_as_lead_id']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });
    }
};
