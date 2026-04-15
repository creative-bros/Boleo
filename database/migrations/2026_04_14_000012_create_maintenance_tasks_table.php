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
        Schema::create('maintenance_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('area')->default('');
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('last_cost', 10, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status')->default('Pendiente');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_tasks');
    }
};
