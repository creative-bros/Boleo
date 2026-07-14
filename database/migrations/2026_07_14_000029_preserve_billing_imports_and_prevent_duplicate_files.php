<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_base_imports', function (Blueprint $table): void {
            $table->string('file_hash', 64)->nullable()->after('stored_path');
            $table->unique(['condominium_profile_id', 'file_hash'], 'billing_base_imports_profile_hash_unique');
        });

        Schema::table('imported_resident_accounts', function (Blueprint $table): void {
            $table->dropUnique('resident_account_profile_unit_unique');
            $table->unique(['billing_base_import_id', 'unit_number', 'tower'], 'resident_account_import_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::table('imported_resident_accounts', function (Blueprint $table): void {
            $table->dropUnique('resident_account_import_unit_unique');
            $table->unique(['condominium_profile_id', 'unit_number', 'tower'], 'resident_account_profile_unit_unique');
        });

        Schema::table('billing_base_imports', function (Blueprint $table): void {
            $table->dropUnique('billing_base_imports_profile_hash_unique');
            $table->dropColumn('file_hash');
        });
    }
};
