<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-use deep-link codes behind the /start linking flow: the web app
 * issues one (GET v1/telegram/link), the user opens t.me/<bot>?start=<code>,
 * and the bot webhook redeems it by stamping users.telegram_chat_id.
 * entity_id mirrors the issuing user's context and stays nullable because
 * users.entity_id is nullable — the chat binding still works without it
 * (only the confirmation reply is skipped, see TelegramAlertChannel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_link_codes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('telegram_chat_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_link_codes');
    }
};
