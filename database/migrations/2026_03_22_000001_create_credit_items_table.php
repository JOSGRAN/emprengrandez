<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['credit_id'], 'credit_items_credit_id_idx');
            $table->index(['product_id'], 'credit_items_product_id_idx');
            $table->index(['product_variant_id'], 'credit_items_variant_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_items');
    }
};
