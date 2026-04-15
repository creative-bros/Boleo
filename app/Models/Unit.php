<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_number',
        'tower',
        'unit_type',
        'owner_name',
        'owner_email',
        'ordinary_fee',
        'indiviso_percentage',
        'extraordinary_fee',
        'parking_rent',
        'storage_rent',
        'parking_spots',
        'storage_rooms',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
