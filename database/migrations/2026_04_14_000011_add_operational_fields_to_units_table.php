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
        Schema::table('units', function (Blueprint $table) {
            $table->decimal('ordinary_fee', 10, 2)->default(0)->after('owner_email');
            $table->decimal('extraordinary_fee', 10, 2)->default(0)->after('ordinary_fee');
            $table->decimal('parking_rent', 10, 2)->default(0)->after('extraordinary_fee');
            $table->decimal('storage_rent', 10, 2)->default(0)->after('parking_rent');
            $table->unsignedInteger('parking_spots')->default(0)->after('storage_rent');
            $table->unsignedInteger('storage_rooms')->default(0)->after('parking_spots');
            $table->unsignedInteger('clothesline_cages')->default(0)->after('storage_rooms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn([
                'ordinary_fee',
                'extraordinary_fee',
                'parking_rent',
                'storage_rent',
                'parking_spots',
                'storage_rooms',
                'clothesline_cages',
            ]);
        });
    }
};
