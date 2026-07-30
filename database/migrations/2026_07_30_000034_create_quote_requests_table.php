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
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number', 32)->nullable()->unique();
            $table->foreignId('condominium_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('condominium_name', 180);
            $table->string('external_reference', 100)->nullable();
            $table->string('source_system', 100)->default('');
            $table->string('contact_name', 180);
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('service_type', 160);
            $table->text('description');
            $table->date('desired_date')->nullable();
            $table->decimal('budget_amount', 12, 2)->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('received');
            $table->json('metadata')->nullable();
            $table->ipAddress('origin_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'external_reference']);
            $table->index(['condominium_profile_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
