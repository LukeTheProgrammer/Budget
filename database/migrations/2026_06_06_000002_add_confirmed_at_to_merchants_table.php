<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            // Null marks a merchant auto-created from an unrecognized descriptor
            // at import time, awaiting human review. Set once confirmed.
            $table->timestamp('confirmed_at')->nullable()->after('name');
        });

        // Merchants that predate review (the curated set) are already trusted.
        DB::table('merchants')->whereNull('confirmed_at')->update(['confirmed_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });
    }
};
