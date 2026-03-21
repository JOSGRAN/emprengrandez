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
        Schema::table('installments', function (Blueprint $table) {
            $table->index(['status', 'due_date'], 'installments_status_due_date_idx');
            $table->index(['credit_id', 'status'], 'installments_credit_status_idx');
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->index(['customer_id', 'status'], 'credits_customer_status_idx');
            $table->index(['status', 'start_date'], 'credits_status_start_date_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'paid_on'], 'payments_status_paid_on_idx');
        });

        Schema::table('whats_app_message_logs', function (Blueprint $table) {
            $table->index(['event', 'installment_id', 'created_at'], 'wam_event_installment_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->dropIndex('installments_status_due_date_idx');
            $table->dropIndex('installments_credit_status_idx');
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->dropIndex('credits_customer_status_idx');
            $table->dropIndex('credits_status_start_date_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_status_paid_on_idx');
        });

        Schema::table('whats_app_message_logs', function (Blueprint $table) {
            $table->dropIndex('wam_event_installment_created_idx');
        });
    }
};
