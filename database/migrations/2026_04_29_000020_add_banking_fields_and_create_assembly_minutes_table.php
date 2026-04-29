<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('condominium_profiles', function (Blueprint $table): void {
            $table->string('bank_account_type')->nullable()->after('account_holder');
            $table->string('bank_agreement')->nullable()->after('clabe');
            $table->string('bank_reference')->nullable()->after('bank_agreement');
            $table->string('bank_branch')->nullable()->after('bank_reference');
            $table->string('bank_contact_email')->nullable()->after('bank_branch');
        });

        Schema::create('assembly_minutes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('condominium_profile_id')->constrained('condominium_profiles')->cascadeOnDelete();
            $table->string('title');
            $table->date('assembly_date')->nullable();
            $table->text('summary')->nullable();
            $table->string('document_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assembly_minutes');

        Schema::table('condominium_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'bank_account_type',
                'bank_agreement',
                'bank_reference',
                'bank_branch',
                'bank_contact_email',
            ]);
        });
    }
};
