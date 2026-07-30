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
            $table->text('decision_notes')->nullable()->after('status');
            $table->timestamp('decided_at')->nullable()->after('decision_notes');
            $table->foreignId('decided_by')->nullable()->after('decided_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropForeign(['decided_by']);
            $table->dropColumn(['decision_notes', 'decided_at', 'decided_by']);
        });
    }
};
