<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('status', 32)->nullable();
            $table->text('recommendation_basis')->nullable();
            $table->decimal('awarded_total', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'code']);
            $table->index(['entity_id', 'status']);
        });

        Schema::create('evaluation_quotations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUlid('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->string('supplier_code', 64);
            $table->string('supplier_name');
            $table->string('supplier_phone')->nullable();
            $table->string('supplier_address')->nullable();
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('vat', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->string('price')->nullable();
            $table->string('quality')->nullable();
            $table->text('lead_time')->nullable();
            $table->text('warranty')->nullable();
            $table->text('payment_terms')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['evaluation_id', 'position']);
        });

        Schema::create('evaluation_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUlid('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->string('item_code', 64);
            $table->text('description');
            $table->decimal('qty', 18, 4);
            $table->string('uom', 32);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['evaluation_id', 'position']);
        });

        Schema::create('evaluation_quotation_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUlid('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignUlid('evaluation_quotation_id')->constrained('evaluation_quotations')->cascadeOnDelete();
            $table->foreignUlid('evaluation_item_id')->constrained('evaluation_items')->cascadeOnDelete();
            $table->string('brand')->nullable();
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4)->default(0);
            $table->boolean('is_selected')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['evaluation_quotation_id', 'evaluation_item_id'],
                'eval_quotation_items_quotation_item_unique',
            );
            $table->index(['evaluation_id', 'is_selected']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_quotation_items');
        Schema::dropIfExists('evaluation_items');
        Schema::dropIfExists('evaluation_quotations');
        Schema::dropIfExists('evaluations');
    }
};
