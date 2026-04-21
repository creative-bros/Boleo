<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('maintenance_expenses', function (Blueprint $table) {
            $table->string('expense_group', 30)->default('fixed')->after('spent_at');
            $table->string('category', 100)->default('Servicio')->after('expense_group');
            $table->date('report_month')->nullable()->after('category');
            $table->string('document_path')->nullable()->after('amount');
            $table->string('document_name')->nullable()->after('document_path');
            $table->text('observations')->nullable()->after('document_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_expenses', function (Blueprint $table) {
            $table->dropColumn([
                'expense_group',
                'category',
                'report_month',
                'document_path',
                'document_name',
                'observations',
            ]);
        });
    }
};
