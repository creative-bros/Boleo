<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'condominium_profile_id',
        'unit_number',
        'tower',
        'sub_tower',
        'unit_type',
        'owner_name',
        'owner_email',
        'owner_phone_primary',
        'owner_phone_secondary',
        'tenant_name',
        'tenant_email',
        'tenant_phone_primary',
        'tenant_phone_secondary',
        'ordinary_fee',
        'indiviso_percentage',
        'extraordinary_fee',
        'parking_rent',
        'storage_rent',
        'parking_spots',
        'parking_assignment',
        'roof_garden',
        'vehicle_tag',
        'pedestrian_tag',
        'storage_rooms',
        'storage_assignment',
        'pet',
        'clothesline_cages',
        'fee',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'ordinary_fee' => 'decimal:2',
            'indiviso_percentage' => 'decimal:4',
            'extraordinary_fee' => 'decimal:2',
            'parking_rent' => 'decimal:2',
            'storage_rent' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Unit $unit): void {
            if (blank($unit->condominium_profile_id)) {
                $unit->condominium_profile_id = CondominiumProfile::query()->orderBy('id')->value('id');
            }
        });
    }

    public function condominiumProfile(): BelongsTo
    {
        return $this->belongsTo(CondominiumProfile::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function residentReceipts(): HasMany
    {
        return $this->hasMany(ResidentReceipt::class);
    }
}
