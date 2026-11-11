<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('source_type', 8)->nullable()->after('purpose');
            $table->unsignedInteger('source_header_id')->nullable()->after('source_type');
            $table->unsignedInteger('source_line_id')->nullable()->after('source_header_id');
            $table->string('source_ref', 64)->nullable()->after('source_line_id');
            $table->string('item_code', 64)->nullable()->after('source_ref');

            $table->index(['source_type', 'source_header_id']);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_header_id']);
            $table->dropColumn([
                'source_type',
                'source_header_id',
                'source_line_id',
                'source_ref',
                'item_code',
            ]);
        });
    }
};
