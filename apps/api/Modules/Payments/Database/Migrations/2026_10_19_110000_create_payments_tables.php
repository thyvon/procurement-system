<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('type', 32);
            $table->string('status', 32)->default('draft');
            $table->string('campus', 64);
            $table->string('division', 64);
            $table->string('department', 64);
            $table->date('request_date');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->string('mode_of_payment', 32)->nullable();
            $table->string('payment_to', 128)->nullable();
            $table->string('bank_account_no', 64)->nullable();
            $table->string('bank_name', 128)->nullable();
            $table->string('advance_request_for', 128)->nullable();
            $table->decimal('total_usd', 18, 2)->default(0);
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'code']);
            $table->index(['entity_id', 'status']);
        });

        Schema::create('cash_request_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUlid('cash_request_id')->constrained('cash_requests')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('description');
            $table->string('currency', 8)->default('USD');
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('amount_usd', 18, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cash_request_id', 'position']);
        });

        Schema::create('cash_clearances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('status', 32)->default('draft');
            $table->foreignUlid('cash_request_id')->constrained('cash_requests')->cascadeOnDelete();
            $table->date('clearance_date');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('total_usd', 18, 2)->default(0);
            $table->decimal('request_amount_usd', 18, 2)->default(0);
            $table->decimal('balance_usd', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'code']);
            $table->index(['entity_id', 'status']);
        });

        Schema::create('cash_clearance_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUlid('cash_clearance_id')->constrained('cash_clearances')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->date('invoice_date')->nullable();
            $table->string('pr_ref', 64)->nullable();
            $table->string('purchaser', 64)->nullable();
            $table->string('description');
            $table->string('vendor');
            $table->string('vendor_phone', 32)->nullable();
            $table->string('campus', 64)->nullable();
            $table->string('division', 64)->nullable();
            $table->string('department', 64)->nullable();
            $table->decimal('qty', 18, 4)->default(0);
            $table->string('uom', 32)->nullable();
            $table->decimal('unit_price_usd', 18, 2)->default(0);
            $table->decimal('discount_usd', 18, 2)->default(0);
            $table->decimal('vat_usd', 18, 2)->default(0);
            $table->decimal('amount_usd', 18, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cash_clearance_id', 'position']);
        });

        Schema::create('advance_liquidations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('status', 32)->default('draft');
            $table->foreignUlid('cash_request_id')->constrained('cash_requests')->cascadeOnDelete();
            $table->date('liquidation_date');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->string('campus', 64);
            $table->string('division', 64);
            $table->string('department', 64);
            $table->decimal('total_actual_expense_usd', 18, 2)->default(0);
            $table->decimal('total_cash_advance_usd', 18, 2)->default(0);
            $table->decimal('remaining_cash_usd', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'code']);
            $table->index(['entity_id', 'status']);
        });

        Schema::create('advance_liquidation_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUlid('advance_liquidation_id')->constrained('advance_liquidations')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('description');
            $table->string('currency', 8)->default('USD');
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('amount_usd', 18, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['advance_liquidation_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_liquidation_lines');
        Schema::dropIfExists('advance_liquidations');
        Schema::dropIfExists('cash_clearance_lines');
        Schema::dropIfExists('cash_clearances');
        Schema::dropIfExists('cash_request_lines');
        Schema::dropIfExists('cash_requests');
    }
};
