<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('linkedin_access_token')->nullable()->after('google_refresh_token');
            $table->string('linkedin_sub')->nullable()->after('linkedin_access_token');
            $table->timestamp('linkedin_connected_at')->nullable()->after('linkedin_sub');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['linkedin_access_token', 'linkedin_sub', 'linkedin_connected_at']);
        });
    }
};
