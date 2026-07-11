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
        Schema::table('transactions', function (Blueprint $table) {
            // Existing rows default to expense, which keeps the schema valid the
            // moment it lands; `transactions:classify-flow-types` then corrects
            // them.
            $table->enum('flow_type', ['expense', 'income', 'transfer', 'refund'])
                ->default('expense')
                ->after('amount_cents');

            $table->enum('flow_type_source', ['auto', 'user'])
                ->default('auto')
                ->after('flow_type');

            $table->foreignId('transfer_pair_id')
                ->nullable()
                ->after('flow_type_source')
                ->constrained('transactions')
                ->nullOnDelete();

            $table->index(['account_id', 'flow_type', 'posted_at']);
            $table->index(['flow_type', 'posted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'flow_type', 'posted_at']);
            $table->dropIndex(['flow_type', 'posted_at']);
            $table->dropConstrainedForeignId('transfer_pair_id');
            $table->dropColumn(['flow_type', 'flow_type_source']);
        });
    }
};
