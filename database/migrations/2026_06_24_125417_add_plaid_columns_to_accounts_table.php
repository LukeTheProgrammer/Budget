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
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('plaid_connection_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('plaid_account_id')->nullable()->after('plaid_connection_id');
            $table->string('type')->nullable()->after('name');
            $table->bigInteger('balance_cents')->nullable()->after('currency');

            $table->unique(['plaid_connection_id', 'plaid_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['plaid_connection_id', 'plaid_account_id']);
            $table->dropConstrainedForeignId('plaid_connection_id');
            $table->dropColumn(['plaid_account_id', 'type', 'balance_cents']);
        });
    }
};
