<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sale_id'], 'sale_items_sale_id_idx');
            $table->index(['product_id'], 'sale_items_product_id_idx');
            $table->index(['product_variant_id'], 'sale_items_variant_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
