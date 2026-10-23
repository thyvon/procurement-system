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
        Schema::table('advance_liquidations', function (Blueprint $table) {
            $table->text('remark')->nullable()->after('remaining_cash_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advance_liquidations', function (Blueprint $table) {
            $table->dropColumn('remark');
        });
    }
};
