<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique(); // e.g. PYR-2026-000123, generated server-side

            // Core relationships
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('salesman_id')->comment('Sales user who owns this shipment')
                ->constrained('users')->cascadeOnDelete();
            $table->foreignId('operation_man_id')->nullable()
                ->comment('Optional handover target. Operations workflow itself is out of MVP scope.')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->comment('Optional originating Sales Lead')
                ->constrained('leads')->nullOnDelete();
            $table->foreignId('shipper_id')->nullable()->constrained('shippers')->nullOnDelete();
            $table->foreignId('consignee_id')->nullable()->constrained('consignees')->nullOnDelete();

            // Business classification (fixed vocab per Pyramidth's existing shipment form)
            $table->string('direction', 20); // import|export|domestic|cross_booking
            $table->string('transport_type', 20); // ocean|air|inland|customs_clearance
            $table->string('shipment_type', 20); // fcl|lcl|bulk|flexi|tank

            // Additional options
            $table->boolean('warehousing')->default(false);
            $table->boolean('sales_lead_flag')->default(false)
                ->comment('The "Sales Lead" checkbox from the original form, distinct from lead_id');
            $table->boolean('dangerous_goods')->default(false);

            $table->string('branch')->nullable();
            $table->date('open_date');

            $table->string('status', 20)->default('open')->index(); // open|handed_over|cancelled
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index(['salesman_id', 'open_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
