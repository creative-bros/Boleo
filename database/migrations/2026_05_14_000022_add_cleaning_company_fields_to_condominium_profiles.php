<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('condominium_profiles', function (Blueprint $table): void {
            $table->string('cleaning_company_name')->nullable()->after('cleaning_staff_phone');
            $table->string('cleaning_company_phone')->nullable()->after('cleaning_company_name');
        });
    }

    public function down(): void
    {
        Schema::table('condominium_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'cleaning_company_name',
                'cleaning_company_phone',
            ]);
        });
    }
};
