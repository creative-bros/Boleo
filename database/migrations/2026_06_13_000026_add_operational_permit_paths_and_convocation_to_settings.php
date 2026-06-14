<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('condominium_profiles', function (Blueprint $table): void {
            $table->string('cleaning_permits_path')->nullable()->after('cleaning_instructions_path');
            $table->string('security_permits_path')->nullable()->after('security_instructions_path');
        });

        Schema::table('assembly_minutes', function (Blueprint $table): void {
            $table->string('convocation_path')->nullable()->after('document_path');
        });
    }

    public function down(): void
    {
        Schema::table('assembly_minutes', function (Blueprint $table): void {
            $table->dropColumn('convocation_path');
        });

        Schema::table('condominium_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'cleaning_permits_path',
                'security_permits_path',
            ]);
        });
    }
};
