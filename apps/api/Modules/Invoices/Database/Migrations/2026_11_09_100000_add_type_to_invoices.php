<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('type', 32)->default('direct_purchase')->after('code');
            $table->index(['entity_id', 'type']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['entity_id', 'type']);
            $table->dropColumn('type');
        });
    }

    /**
     * Every invoice that exists before this flag was introduced was created
     * through the direct purchase flow (the only one that existed), so any
     * row still without a type is one of those.
     */
    private function backfill(): void
    {
        DB::table('invoices')
            ->whereNull('type')
            ->update(['type' => 'direct_purchase']);
    }
};
