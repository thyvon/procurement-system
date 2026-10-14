<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campuses', function (Blueprint $table) {
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

        Schema::create('divisions', function (Blueprint $table) {
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

        Schema::create('departments', function (Blueprint $table) {
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

        // A department can serve several divisions and a division covers
        // several departments — classic many-to-many.
        Schema::create('department_division', function (Blueprint $table) {
            $table->foreignUlid('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->foreignUlid('department_id')->constrained('departments')->cascadeOnDelete();
            $table->primary(['division_id', 'department_id']);
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_division');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('campuses');
    }
};
