<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CondominiumProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'commercial_name',
        'tax_id',
        'address',
        'latitude',
        'longitude',
        'ordinary_fee_amount',
        'fee_type',
        'departments_count',
        'parking_spaces_count',
        'storage_rooms_count',
        'clothesline_cages_count',
        'security_booth',
        'admin_type',
        'admin_name',
        'assistant_admin_names',
        'assistant_admin_phone',
        'admin_registration_path',
        'admin_registration_documents',
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
        'pool_enabled',
        'wading_pool_enabled',
        'event_hall_enabled',
        'roof_garden_enabled',
        'yoga_room_enabled',
        'game_room_enabled',
        'gym_enabled',
        'grill_enabled',
        'moving_hours',
        'work_hours',
        'meeting_hours',
        'regulations_path',
        'parking_map_path',
        'property_regime_path',
        'cleaning_staff_name',
        'cleaning_staff_phone',
        'cleaning_staff_contact',
        'cleaning_instructions_path',
        'cleaning_permits_path',
        'security_staff_name',
        'security_staff_phone',
        'security_staff_contact',
        'security_instructions_path',
        'security_permits_path',
        'bank',
        'account_holder',
        'bank_account_type',
        'account_number',
        'clabe',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'ordinary_fee_amount' => 'decimal:2',
            'admin_registration_documents' => 'array',
            'security_booth' => 'boolean',
            'elevators_enabled' => 'boolean',
            'cisterns_enabled' => 'boolean',
            'water_tanks_enabled' => 'boolean',
            'hydropneumatics_enabled' => 'boolean',
            'pool_enabled' => 'boolean',
            'wading_pool_enabled' => 'boolean',
            'event_hall_enabled' => 'boolean',
            'roof_garden_enabled' => 'boolean',
            'yoga_room_enabled' => 'boolean',
            'game_room_enabled' => 'boolean',
            'gym_enabled' => 'boolean',
            'grill_enabled' => 'boolean',
        ];
    }

    public function assemblyMinutes(): HasMany
    {
        return $this->hasMany(AssemblyMinute::class);
    }
}
