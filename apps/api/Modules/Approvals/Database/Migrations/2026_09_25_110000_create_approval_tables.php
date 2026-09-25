<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('subject_type', 64);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'subject_type']);
        });

        Schema::create('approval_flows', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUlid('approval_setting_id')->constrained('approval_settings')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->decimal('min_amount', 18, 2)->default(0);
            $table->decimal('max_amount', 18, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'code']);
            $table->index(['approval_setting_id', 'min_amount']);
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUlid('approval_flow_id')->constrained('approval_flows')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('key', 32);
            $table->string('label');
            $table->string('action_mode', 16)->default('decide');
            $table->json('allowed_actions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['approval_flow_id', 'position']);
            $table->unique(['approval_flow_id', 'key']);
        });

        Schema::create('toca_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 64);
            $table->decimal('min_amount', 18, 2)->default(0);
            $table->decimal('max_amount', 18, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'subject_type']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('subject_type', 64);
            $table->string('subject_id', 26);
            $table->foreignUlid('approval_setting_id')->constrained('approval_settings');
            $table->foreignUlid('approval_flow_id')->constrained('approval_flows');
            $table->decimal('amount_snapshot', 18, 2)->default(0);
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('current_position')->nullable();
            $table->foreignId('current_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entity_id', 'status']);
            $table->index(['current_assignee_id', 'status']);
            $table->index(['entity_id', 'subject_type', 'subject_id']);
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUlid('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->unsignedInteger('step_position');
            $table->string('step_key', 32);
            $table->string('action', 16);
            $table->text('comment')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['approval_request_id', 'step_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('toca_entries');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_flows');
        Schema::dropIfExists('approval_settings');
    }
};
