<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id', 100)->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();

            $table->unique(['user_id', 'session_id'], 'user_sessions_user_session_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
