<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class City extends Model { protected $fillable = ['source_id', 'country_id', 'name', 'state_name', 'latitude', 'longitude']; public function country(): BelongsTo { return $this->belongsTo(Country::class); } }
