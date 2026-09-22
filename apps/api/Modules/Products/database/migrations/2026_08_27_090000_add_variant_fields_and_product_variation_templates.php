<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->foreignUlid('sub_unit_id')->nullable()->after('option_values')
                ->constrained('uom_sub_units')->nullOnDelete();
        });

        // Additive only: the existing unique(entity_id, product_id, name) stays,
        // since VariationService::merge() and the importer already write under it.
        // The matrix needs SKUs to be unique within a product, not globally --
        // code is nullable, so blank SKUs remain unconstrained.
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['entity_id', 'product_id', 'code']);
        });

        Schema::create('product_variation_template', function (Blueprint $table) {
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUlid('variation_template_id')->constrained('variation_templates')->cascadeOnDelete();

            $table->primary(['product_id', 'variation_template_id']);
            $table->index('variation_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variation_template');

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['entity_id', 'product_id', 'code']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sub_unit_id');
            $table->dropColumn('description');
        });
    }
};
