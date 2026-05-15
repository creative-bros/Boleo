<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('condominium_profiles', function (Blueprint $table): void {
            $table->json('admin_registration_documents')->nullable()->after('admin_registration_path');
            $table->string('parking_map_path')->nullable()->after('regulations_path');
            $table->string('property_regime_path')->nullable()->after('parking_map_path');
            $table->string('cleaning_instructions_path')->nullable()->after('cleaning_staff_contact');
            $table->string('security_staff_secondary_name')->nullable()->after('security_staff_contact');
            $table->string('security_staff_secondary_phone')->nullable()->after('security_staff_secondary_name');
            $table->string('security_staff_secondary_contact')->nullable()->after('security_staff_secondary_phone');
            $table->string('security_instructions_path')->nullable()->after('security_staff_secondary_contact');
        });

        Schema::table('assembly_minutes', function (Blueprint $table): void {
            $table->string('duration')->nullable()->after('assembly_date');
        });
    }

    public function down(): void
    {
        Schema::table('assembly_minutes', function (Blueprint $table): void {
            $table->dropColumn('duration');
        });

        Schema::table('condominium_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'admin_registration_documents',
                'parking_map_path',
                'property_regime_path',
                'cleaning_instructions_path',
                'security_staff_secondary_name',
                'security_staff_secondary_phone',
                'security_staff_secondary_contact',
                'security_instructions_path',
            ]);
        });
    }
};
