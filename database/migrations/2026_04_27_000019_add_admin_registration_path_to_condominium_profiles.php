<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('condominium_profiles', function (Blueprint $table) {
            $table->string('admin_registration_path')->default('')->after('assistant_admin_phone');
        });
    }

    public function down(): void
    {
        Schema::table('condominium_profiles', function (Blueprint $table) {
            $table->dropColumn('admin_registration_path');
        });
    }
};
