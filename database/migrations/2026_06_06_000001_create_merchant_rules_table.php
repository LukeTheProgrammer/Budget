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
        Schema::create('merchant_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            // 'prefix' matches the start of a raw descriptor (case-insensitive);
            // 'regex' matches a full PCRE pattern. Exact matches stay in aliases.
            $table->string('match_type', 20);
            $table->string('pattern', 500);
            // Lower priority is evaluated first, so more specific rules can win.
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_rules');
    }
};
