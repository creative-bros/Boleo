<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('condominium_profiles', function (Blueprint $table): void {
            $table->boolean('grill_enabled')->default(false)->after('gym_enabled');
            $table->text('cleaning_instructions')->nullable()->after('cleaning_staff_contact');
            $table->text('security_instructions')->nullable()->after('security_staff_contact');
        });
    }

    public function down(): void
    {
        Schema::table('condominium_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'grill_enabled',
                'cleaning_instructions',
                'security_instructions',
            ]);
        });
    }
};
