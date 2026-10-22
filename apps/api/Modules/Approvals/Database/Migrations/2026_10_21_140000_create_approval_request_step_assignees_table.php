<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_request_step_assignees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUlid('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->unsignedInteger('step_position');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->unique(['approval_request_id', 'step_position', 'user_id'], 'request_step_user_unique');
            $table->index(['user_id', 'acted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_request_step_assignees');
    }
};
