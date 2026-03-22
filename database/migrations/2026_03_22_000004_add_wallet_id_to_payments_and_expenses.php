<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('wallet_id')
                ->nullable()
                ->after('installment_id')
                ->constrained('wallets')
                ->nullOnDelete()
                ->index();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('wallet_id')
                ->nullable()
                ->after('expense_category_id')
                ->constrained('wallets')
                ->nullOnDelete()
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wallet_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wallet_id');
        });
    }
};
