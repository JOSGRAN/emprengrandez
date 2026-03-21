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
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->decimal('principal_amount', 12, 2);
            $table->string('interest_type', 20)->default('none')->index();
            $table->decimal('interest_rate', 8, 5)->default(0);
            $table->string('calculation_method', 20)->default('direct')->index();
            $table->string('frequency', 20)->default('monthly')->index();
            $table->unsignedSmallInteger('installments_count');
            $table->decimal('total_interest', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0)->index();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
