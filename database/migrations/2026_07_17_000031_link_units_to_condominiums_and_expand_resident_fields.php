<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->foreignId('condominium_profile_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
            $table->string('sub_tower')->default('')->after('tower');
            $table->string('owner_phone_primary')->default('')->after('owner_email');
            $table->string('owner_phone_secondary')->default('')->after('owner_phone_primary');
            $table->string('tenant_name')->default('')->after('owner_phone_secondary');
            $table->string('tenant_email')->default('')->after('tenant_name');
            $table->string('tenant_phone_primary')->default('')->after('tenant_email');
            $table->string('tenant_phone_secondary')->default('')->after('tenant_phone_primary');
            $table->string('parking_assignment')->default('')->after('parking_spots');
            $table->string('roof_garden')->default('')->after('parking_assignment');
            $table->string('vehicle_tag')->default('')->after('roof_garden');
            $table->string('pedestrian_tag')->default('')->after('vehicle_tag');
            $table->string('storage_assignment')->default('')->after('storage_rooms');
            $table->string('pet')->default('')->after('storage_assignment');
        });

        $firstProfileId = DB::table('condominium_profiles')->orderBy('id')->value('id');

        if ($firstProfileId) {
            DB::table('units')
                ->whereNull('condominium_profile_id')
                ->update(['condominium_profile_id' => $firstProfileId]);
        }
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('condominium_profile_id');
            $table->dropColumn([
                'sub_tower',
                'owner_phone_primary',
                'owner_phone_secondary',
                'tenant_name',
                'tenant_email',
                'tenant_phone_primary',
                'tenant_phone_secondary',
                'parking_assignment',
                'roof_garden',
                'vehicle_tag',
                'pedestrian_tag',
                'storage_assignment',
                'pet',
            ]);
        });
    }
};
