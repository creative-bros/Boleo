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
        Schema::create('condominium_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('commercial_name')->default('');
            $table->string('tax_id')->default('');
            $table->string('address')->default('');
            $table->decimal('ordinary_fee_amount', 10, 2)->default(0);
            $table->string('fee_type', 20)->default('standard');
            $table->unsignedInteger('departments_count')->default(0);
            $table->unsignedInteger('parking_spaces_count')->default(0);
            $table->unsignedInteger('storage_rooms_count')->default(0);
            $table->unsignedInteger('clothesline_cages_count')->default(0);
            $table->boolean('security_booth')->default(false);
            $table->string('admin_name')->default('');
            $table->string('admin_email')->default('');
            $table->string('admin_phone')->default('');
            $table->boolean('elevators_enabled')->default(false);
            $table->unsignedInteger('elevators_count')->default(0);
            $table->boolean('cisterns_enabled')->default(false);
            $table->unsignedInteger('cisterns_count')->default(0);
            $table->boolean('water_tanks_enabled')->default(false);
            $table->unsignedInteger('water_tanks_count')->default(0);
            $table->boolean('hydropneumatics_enabled')->default(false);
            $table->unsignedInteger('hydropneumatics_count')->default(0);
            $table->string('bank')->default('');
            $table->string('account_holder')->default('');
            $table->string('account_number')->default('');
            $table->string('clabe')->default('');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('condominium_profiles');
    }
};
