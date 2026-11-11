<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_lines', 'source_type')) {
                $table->string('source_type', 8)->nullable()->after('purpose');
                $table->unsignedInteger('source_header_id')->nullable()->after('source_type');
                $table->unsignedInteger('source_line_id')->nullable()->after('source_header_id');
                $table->index(['source_type', 'source_header_id']);
            }
            if (! Schema::hasColumn('invoice_lines', 'pr_ref')) {
                $table->string('pr_ref', 64)->nullable()->after('source_line_id');
            }
            if (! Schema::hasColumn('invoice_lines', 'po_ref')) {
                $table->string('po_ref', 64)->nullable()->after('pr_ref');
            }
            if (! Schema::hasColumn('invoice_lines', 'item_code')) {
                $table->string('item_code', 64)->nullable()->after('po_ref');
            }
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
                'pr_ref',
                'po_ref',
                'item_code',
            ]);
        });
    }
};
