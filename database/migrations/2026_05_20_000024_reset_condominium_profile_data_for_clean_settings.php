<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('condominium_profiles')->update([
            'commercial_name' => '',
            'tax_id' => '',
            'address' => '',
            'latitude' => null,
            'longitude' => null,
            'ordinary_fee_amount' => 0,
            'fee_type' => 'standard',
            'departments_count' => 0,
            'parking_spaces_count' => 0,
            'storage_rooms_count' => 0,
            'clothesline_cages_count' => 0,
            'security_booth' => false,
            'admin_type' => '',
            'admin_name' => 'Rodolfo Chiquillo Quevedo',
            'assistant_admin_names' => '',
            'assistant_admin_phone' => '',
            'admin_registration_path' => '',
            'admin_registration_documents' => json_encode([]),
            'admin_email' => 'Boleo54@yahoo.com.mx',
            'admin_phone' => '5530707950',
            'elevators_enabled' => false,
            'elevators_count' => 0,
            'cisterns_enabled' => false,
            'cisterns_count' => 0,
            'water_tanks_enabled' => false,
            'water_tanks_count' => 0,
            'hydropneumatics_enabled' => false,
            'hydropneumatics_count' => 0,
            'pool_enabled' => false,
            'wading_pool_enabled' => false,
            'event_hall_enabled' => false,
            'roof_garden_enabled' => false,
            'yoga_room_enabled' => false,
            'game_room_enabled' => false,
            'gym_enabled' => false,
            'grill_enabled' => false,
            'moving_hours' => '',
            'work_hours' => '',
            'meeting_hours' => '',
            'regulations_path' => '',
            'parking_map_path' => '',
            'property_regime_path' => '',
            'cleaning_staff_name' => '',
            'cleaning_staff_phone' => '',
            'cleaning_staff_contact' => '',
            'cleaning_instructions_path' => '',
            'security_staff_name' => '',
            'security_staff_phone' => '',
            'security_staff_contact' => '',
            'security_staff_secondary_name' => '',
            'security_staff_secondary_phone' => '',
            'security_staff_secondary_contact' => '',
            'security_instructions_path' => '',
            'bank' => '',
            'account_holder' => '',
            'bank_account_type' => '',
            'account_number' => '',
            'clabe' => '',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Este reset es intencionalmente irreversible para no restaurar datos operativos previos.
    }
};
