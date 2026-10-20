<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['campuses', 'divisions', 'departments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('short_code', 16)->nullable()->after('name');
            });

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique(['entity_id', 'short_code']);
            });
        }
    }

    public function down(): void
    {
        foreach (['campuses', 'divisions', 'departments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropUnique(['entity_id', 'short_code']);
                $table->dropColumn('short_code');
            });
        }
    }
};
