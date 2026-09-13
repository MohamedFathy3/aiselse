<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A Shipper is the party handing off goods on a shipment. It may or may not be
        // the Client itself (e.g. on an import, the Client is the consignee and a third
        // party overseas is the shipper) - so it is its own lightweight record, optionally
        // linked back to a Client/Contact when the shipper IS an existing CRM record.
        Schema::create('shippers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shippers');
    }
};
