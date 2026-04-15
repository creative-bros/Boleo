<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CondominiumProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'commercial_name',
        'tax_id',
        'address',
        'ordinary_fee_amount',
        'fee_type',
        'departments_count',
        'parking_spaces_count',
        'storage_rooms_count',
        'clothesline_cages_count',
        'security_booth',
        'admin_name',
        'admin_email',
        'admin_phone',
        'elevators_enabled',
        'elevators_count',
        'cisterns_enabled',
        'cisterns_count',
        'water_tanks_enabled',
        'water_tanks_count',
        'hydropneumatics_enabled',
        'hydropneumatics_count',
        'bank',
        'account_holder',
        'account_number',
        'clabe',
    ];

    protected function casts(): array
    {
        return [
            'ordinary_fee_amount' => 'decimal:2',
            'security_booth' => 'boolean',
            'elevators_enabled' => 'boolean',
            'cisterns_enabled' => 'boolean',
            'water_tanks_enabled' => 'boolean',
            'hydropneumatics_enabled' => 'boolean',
        ];
    }
}
