<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportedResidentAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'condominium_profile_id',
        'unit_id',
        'unit_number',
        'tower',
        'sub_tower',
        'owner_name',
        'total_debt',
        'status',
        'year_statuses',
        'observations',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'total_debt' => 'decimal:2',
            'year_statuses' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function condominiumProfile(): BelongsTo
    {
        return $this->belongsTo(CondominiumProfile::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
