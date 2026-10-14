<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('uom', 32)->nullable()->after('description');
            $table->string('campus', 64)->nullable()->after('uom');
            $table->string('division', 64)->nullable()->after('campus');
            $table->string('department', 64)->nullable()->after('division');
            $table->string('location', 128)->nullable()->after('department');
            $table->string('purpose')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn([
                'uom',
                'campus',
                'division',
                'department',
                'location',
                'purpose',
            ]);
        });
    }
};
