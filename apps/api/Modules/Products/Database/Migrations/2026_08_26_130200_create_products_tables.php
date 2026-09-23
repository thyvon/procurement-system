<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('name_km')->nullable();
            $table->text('description')->nullable();
            $table->enum('product_type', ['single', 'variable'])->default('single');
            $table->foreignUlid('product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignUlid('product_group_id')->nullable()->constrained('product_groups')->nullOnDelete();
            $table->foreignUlid('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignUlid('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
            $table->foreignUlid('sub_unit_id')->nullable()->constrained('uom_sub_units')->nullOnDelete();
            $table->decimal('purchase_price', 18, 4)->nullable();
            $table->decimal('sub_unit_purchase_price', 18, 4)->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'code']);
            $table->index(['entity_id', 'is_active']);
            $table->index(['product_category_id']);
            $table->index(['brand_id']);
            $table->index(['product_group_id']);
            $table->fullText(['name', 'name_km']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('code', 64)->nullable();
            $table->string('name');
            $table->json('option_values');
            $table->decimal('purchase_price', 18, 4)->nullable();
            $table->decimal('sub_unit_purchase_price', 18, 4)->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'product_id', 'name']);
            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
