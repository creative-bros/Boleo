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
        'billing_base_import_id',
        'unit_id',
        'unit_number',
        'tower',
        'sub_tower',
        'source_row_number',
        'owner_name',
        'total_debt',
        'status',
        'year_statuses',
        'raw_payload',
        'observations',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'total_debt' => 'decimal:2',
            'year_statuses' => 'array',
            'raw_payload' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function condominiumProfile(): BelongsTo
    {
        return $this->belongsTo(CondominiumProfile::class);
    }

    public function billingBaseImport(): BelongsTo
    {
        return $this->belongsTo(BillingBaseImport::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
