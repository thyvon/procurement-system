<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variation_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'name']);
        });

        Schema::create('variation_template_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('variation_template_id')->constrained('variation_templates')->cascadeOnDelete();
            $table->string('value');
            $table->unsignedInteger('sort_order')->default(0);

            $table->unique(['variation_template_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variation_template_options');
        Schema::dropIfExists('variation_templates');
    }
};
