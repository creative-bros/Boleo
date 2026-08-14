<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imported_resident_accounts', function (Blueprint $table): void {
            $table->longText('custom_letter_text')->nullable()->after('observations');
        });
    }

    public function down(): void
    {
        Schema::table('imported_resident_accounts', function (Blueprint $table): void {
            $table->dropColumn('custom_letter_text');
        });
    }
};
