<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bot API chat id of a user's direct chat with the approval-alerts bot.
 * Null = the user only receives in-app notifications. Populated manually
 * for now (a /start webhook is a follow-up), hence no mass assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_chat_id')->nullable()->unique()->after('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['telegram_chat_id']);
            $table->dropColumn('telegram_chat_id');
        });
    }
};
