<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_base_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('condominium_profile_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_path');
            $table->unsignedInteger('imported_rows')->default(0);
            $table->string('status')->default('procesada');
            $table->text('notes')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        Schema::table('imported_resident_accounts', function (Blueprint $table): void {
            $table->foreignId('billing_base_import_id')->nullable()->after('condominium_profile_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('source_row_number')->nullable()->after('sub_tower');
            $table->json('raw_payload')->nullable()->after('year_statuses');
        });
    }

    public function down(): void
    {
        Schema::table('imported_resident_accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('billing_base_import_id');
            $table->dropColumn(['source_row_number', 'raw_payload']);
        });

        Schema::dropIfExists('billing_base_imports');
    }
};
