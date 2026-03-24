<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('notification_templates')
            ->where('channel', '!=', 'waha')
            ->update(['channel' => 'waha']);

        DB::table('whats_app_message_logs')
            ->where('channel', '!=', 'waha')
            ->update(['channel' => 'waha']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE notification_templates ALTER COLUMN channel SET DEFAULT 'waha'");
            DB::statement("ALTER TABLE whats_app_message_logs ALTER COLUMN channel SET DEFAULT 'waha'");
        }
    }

    public function down(): void {}
};
