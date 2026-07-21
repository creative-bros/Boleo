<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('condominium_profiles', function (Blueprint $table): void {
            $table->string('report_signature_path')->nullable()->after('no_debt_letter_template_path');
        });
    }

    public function down(): void
    {
        Schema::table('condominium_profiles', function (Blueprint $table): void {
            $table->dropColumn('report_signature_path');
        });
    }
};
