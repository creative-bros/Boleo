<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('condominium_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('amount_due', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('status')->default('pendiente');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['condominium_profile_id', 'unit_id', 'period_year', 'period_month'],
                'resident_receipts_profile_unit_period_unique'
            );
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('resident_receipt_id')
                ->nullable()
                ->after('unit_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('resident_receipt_id');
        });

        Schema::dropIfExists('resident_receipts');
    }
};
