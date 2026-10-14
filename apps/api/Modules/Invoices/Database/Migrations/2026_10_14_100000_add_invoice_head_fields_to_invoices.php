<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('supplier_phone', 32)->nullable()->after('supplier_code');
            $table->string('supplier_address')->nullable()->after('supplier_phone');
            $table->string('transaction_type', 32)->nullable()->after('supplier_address');
            $table->string('cash_reference', 64)->nullable()->after('transaction_type');
            $table->string('payment_term', 32)->nullable()->after('cash_reference');
            $table->decimal('vat_percent', 5, 2)->nullable()->after('vat_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'supplier_phone',
                'supplier_address',
                'transaction_type',
                'cash_reference',
                'payment_term',
                'vat_percent',
            ]);
        });
    }
};
