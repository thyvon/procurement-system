<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Steps still decide normally when hidden; only the official print
        // sheet drops their signature column.
        Schema::table('approval_steps', function (Blueprint $table): void {
            $table->boolean('show_on_print')->default(true)->after('allowed_actions');
        });
    }

    public function down(): void
    {
        Schema::table('approval_steps', function (Blueprint $table): void {
            $table->dropColumn('show_on_print');
        });
    }
};
