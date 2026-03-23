<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'wallet_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('wallet_id')
                    ->nullable()
                    ->after('installment_id')
                    ->constrained('wallets')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('expenses', 'wallet_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('wallet_id')
                    ->nullable()
                    ->after('expense_category_id')
                    ->constrained('wallets')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'wallet_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('wallet_id');
            });
        }

        if (Schema::hasColumn('expenses', 'wallet_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropConstrainedForeignId('wallet_id');
            });
        }
    }
};
