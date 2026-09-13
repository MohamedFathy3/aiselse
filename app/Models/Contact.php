<?php

namespace App\Models;

use App\Enums\ContactableType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contactable_type', 'contactable_id',
        'name', 'job_title', 'email', 'phone', 'mobile', 'whatsapp', 'notes', 'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'contactable_type' => ContactableType::class,
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Manual "polymorphic" accessor since we store a plain string discriminator
     * ('lead'|'client') rather than a class-name morph, keeping the DB decoupled
     * from PHP namespaces (see migration comment).
     */
    public function contactable(): Lead|Client|null
    {
        return match ($this->contactable_type) {
            ContactableType::Lead => Lead::find($this->contactable_id),
            ContactableType::Client => Client::find($this->contactable_id),
            default => null,
        };
    }
}
