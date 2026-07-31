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
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('client_name', 180)->nullable()->after('source_system');
            $table->string('client_email')->nullable()->after('client_name');
            $table->string('client_phone', 40)->nullable()->after('client_email');
            $table->string('property_location', 255)->nullable()->after('client_phone');
            $table->string('monthly_budget', 100)->nullable()->after('property_location');
            $table->boolean('has_administration')->nullable()->after('monthly_budget');
            $table->boolean('has_prosoc_certification')->nullable()->after('has_administration');
            $table->string('apartment_count', 80)->nullable()->after('has_prosoc_certification');
            $table->text('comment')->nullable()->after('apartment_count');
            $table->string('consultation_date', 100)->nullable()->after('comment');
            $table->string('source', 100)->nullable()->after('consultation_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn([
                'client_name',
                'client_email',
                'client_phone',
                'property_location',
                'monthly_budget',
                'has_administration',
                'has_prosoc_certification',
                'apartment_count',
                'comment',
                'consultation_date',
                'source',
            ]);
        });
    }
};
