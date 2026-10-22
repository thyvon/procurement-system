<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bilingual signature titles on the official print sheets: the Khmer
        // name of the step, frozen into the snapshot like the English label.
        Schema::table('approval_steps', function (Blueprint $table): void {
            $table->string('label_kh', 255)->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('approval_steps', function (Blueprint $table): void {
            $table->dropColumn('label_kh');
        });
    }
};
