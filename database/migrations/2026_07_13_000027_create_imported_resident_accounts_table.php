<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imported_resident_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('condominium_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit_number');
            $table->string('tower')->nullable();
            $table->string('sub_tower')->nullable();
            $table->string('owner_name');
            $table->decimal('total_debt', 12, 2)->default(0);
            $table->string('status')->default('no_adeudo');
            $table->json('year_statuses')->nullable();
            $table->text('observations')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['condominium_profile_id', 'unit_number', 'tower'], 'resident_account_profile_unit_unique');
        });

        Schema::table('condominium_profiles', function (Blueprint $table): void {
            $table->string('debt_letter_template_path')->nullable()->after('clabe');
            $table->string('no_debt_letter_template_path')->nullable()->after('debt_letter_template_path');
        });
    }

    public function down(): void
    {
        Schema::table('condominium_profiles', function (Blueprint $table): void {
            $table->dropColumn(['debt_letter_template_path', 'no_debt_letter_template_path']);
        });

        Schema::dropIfExists('imported_resident_accounts');
    }
};
