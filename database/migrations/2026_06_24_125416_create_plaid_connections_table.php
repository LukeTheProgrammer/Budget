<?php

use App\Enums\PlaidConnectionStatus;
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
        Schema::create('plaid_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plaid_item_id')->unique();
            $table->text('access_token');
            $table->string('institution_id')->nullable();
            $table->string('institution_name')->nullable();
            $table->string('status')->default(PlaidConnectionStatus::Active->value);
            $table->text('transactions_cursor')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plaid_connections');
    }
};
