<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toca_entries', function (Blueprint $table): void {
            $table->string('step_key', 32)->nullable()->after('subject_type');
            $table->index(['subject_type', 'step_key']);
        });
    }

    public function down(): void
    {
        Schema::table('toca_entries', function (Blueprint $table): void {
            $table->dropIndex(['subject_type', 'step_key']);
            $table->dropColumn('step_key');
        });
    }
};
