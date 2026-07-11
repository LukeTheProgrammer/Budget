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
        Schema::table('merchants', function (Blueprint $table) {
            // The user's learned classification rule for this merchant. Null
            // means "no rule yet — fall back to the automatic heuristics".
            $table->enum('default_flow_type', ['expense', 'income', 'transfer', 'refund'])
                ->nullable()
                ->after('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('default_flow_type');
        });
    }
};
