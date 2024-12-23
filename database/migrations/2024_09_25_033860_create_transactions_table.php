<?php

use App\Models\Vendor;
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
            $table->foreignIdFor(Vendor::class);
            $table->string('hash')->nullable();
            $table->dateTime('transaction_date')->nullable();
            $table->dateTime('post_date')->nullable();
            $table->string('category')->nullable();
            $table->string('type')->nullable();
            $table->decimal('amount', total: 10, places: 2)->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
