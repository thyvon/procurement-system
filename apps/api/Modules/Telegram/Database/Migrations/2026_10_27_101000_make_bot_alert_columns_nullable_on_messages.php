<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bot API alerts reuse the telegram_messages audit table but belong to no
 * TDLib account and no contact — only a user's direct chat. The column
 * types stay byte-identical to the original foreignUlid() definitions so
 * the existing foreign keys remain valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->char('telegram_account_id', 26)->nullable()->change();
            $table->char('telegram_contact_id', 26)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->char('telegram_account_id', 26)->nullable(false)->change();
            $table->char('telegram_contact_id', 26)->nullable(false)->change();
        });
    }
};
