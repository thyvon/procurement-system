<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telegram @usernames as an optional secondary identifier: phone stays the
 * stable key, the username enables resolution via searchPublicChat without
 * uploading contacts. One of the two must be present at creation time
 * (enforced by the FormRequest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->string('username', 32)->nullable()->after('phone');
            $table->string('phone', 32)->nullable()->change();
            $table->unique(['entity_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::table('telegram_contacts', function (Blueprint $table) {
            $table->dropUnique(['entity_id', 'username']);
            $table->dropColumn('username');
        });
    }
};
