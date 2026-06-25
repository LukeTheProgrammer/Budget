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
        Schema::create('tags', function (Blueprint $table) {
            $table->string('slug', 60)->primary();
            $table->string('name', 50);
            $table->timestamps();
        });

        Schema::create('tag_transaction', function (Blueprint $table) {
            $table->string('tag_slug', 60);
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();

            $table->foreign('tag_slug')->references('slug')->on('tags')->cascadeOnDelete();
            $table->unique(['tag_slug', 'transaction_id']);
        });

        Schema::create('merchant_default_tag', function (Blueprint $table) {
            $table->string('tag_slug', 60);
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();

            $table->foreign('tag_slug')->references('slug')->on('tags')->cascadeOnDelete();
            $table->unique(['tag_slug', 'merchant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_default_tag');
        Schema::dropIfExists('tag_transaction');
        Schema::dropIfExists('tags');
    }
};
