<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('condominium_profiles', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('admin_type')->default('')->after('security_booth');
            $table->text('assistant_admin_names')->default('')->after('admin_name');
            $table->string('assistant_admin_phone')->default('')->after('assistant_admin_names');

            $table->boolean('pool_enabled')->default(false)->after('hydropneumatics_count');
            $table->boolean('wading_pool_enabled')->default(false)->after('pool_enabled');
            $table->boolean('event_hall_enabled')->default(false)->after('wading_pool_enabled');
            $table->boolean('roof_garden_enabled')->default(false)->after('event_hall_enabled');
            $table->boolean('yoga_room_enabled')->default(false)->after('roof_garden_enabled');
            $table->boolean('game_room_enabled')->default(false)->after('yoga_room_enabled');
            $table->boolean('gym_enabled')->default(false)->after('game_room_enabled');

            $table->string('moving_hours')->default('')->after('gym_enabled');
            $table->string('work_hours')->default('')->after('moving_hours');
            $table->string('meeting_hours')->default('')->after('work_hours');
            $table->string('regulations_path')->default('')->after('meeting_hours');

            $table->string('cleaning_staff_name')->default('')->after('regulations_path');
            $table->string('cleaning_staff_phone')->default('')->after('cleaning_staff_name');
            $table->string('cleaning_staff_contact')->default('')->after('cleaning_staff_phone');
            $table->string('security_staff_name')->default('')->after('cleaning_staff_contact');
            $table->string('security_staff_phone')->default('')->after('security_staff_name');
            $table->string('security_staff_contact')->default('')->after('security_staff_phone');
        });
    }

    public function down(): void
    {
        Schema::table('condominium_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'latitude',
                'longitude',
                'admin_type',
                'assistant_admin_names',
                'assistant_admin_phone',
                'pool_enabled',
                'wading_pool_enabled',
                'event_hall_enabled',
                'roof_garden_enabled',
                'yoga_room_enabled',
                'game_room_enabled',
                'gym_enabled',
                'moving_hours',
                'work_hours',
                'meeting_hours',
                'regulations_path',
                'cleaning_staff_name',
                'cleaning_staff_phone',
                'cleaning_staff_contact',
                'security_staff_name',
                'security_staff_phone',
                'security_staff_contact',
            ]);
        });
    }
};
