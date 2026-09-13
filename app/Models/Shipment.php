<?php

namespace App\Models;

use App\Enums\ShipmentDirection;
use App\Enums\ShipmentType;
use App\Enums\TransportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_number', 'client_id', 'salesman_id', 'operation_man_id', 'agent_id',
        'lead_id', 'shipper_id', 'consignee_id',
        'direction', 'transport_type', 'shipment_type',
        'warehousing', 'sales_lead_flag', 'dangerous_goods',
        'branch', 'open_date', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'direction' => ShipmentDirection::class,
            'transport_type' => TransportType::class,
            'shipment_type' => ShipmentType::class,
            'warehousing' => 'boolean',
            'sales_lead_flag' => 'boolean',
            'dangerous_goods' => 'boolean',
            'open_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesman_id');
    }

    public function operationMan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operation_man_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }

    public function consignee(): BelongsTo
    {
        return $this->belongsTo(Consignee::class);
    }
}
