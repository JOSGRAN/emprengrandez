<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->string('type', 20)->index();
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->string('reference_type', 50)->nullable()->index();
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->boolean('is_reversal')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['wallet_id', 'created_at'], 'wallet_tx_wallet_created_idx');
            $table->unique(['wallet_id', 'reference_type', 'reference_id', 'is_reversal'], 'wallet_tx_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
