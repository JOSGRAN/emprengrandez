<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->date('sold_on')->index();
            $table->decimal('total', 12, 2);
            $table->string('payment_method', 20)->default('cash')->index();
            $table->string('status', 20)->default('posted')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sold_on'], 'sales_status_sold_on_idx');
            $table->index(['wallet_id', 'sold_on'], 'sales_wallet_sold_on_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
