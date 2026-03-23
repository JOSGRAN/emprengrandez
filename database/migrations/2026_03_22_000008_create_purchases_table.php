<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('supplier_name')->nullable()->index();
            $table->date('purchased_on')->index();
            $table->decimal('total', 12, 2);
            $table->string('status', 20)->default('paid')->index();
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'purchased_on'], 'purchases_status_purchased_on_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
