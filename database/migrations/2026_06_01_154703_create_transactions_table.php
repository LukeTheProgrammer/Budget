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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('description')->nullable();
            $table->date('posted_at');
            $table->char('import_hash', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['account_id', 'import_hash']);
            $table->index(['account_id', 'posted_at']);
            $table->index('posted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
