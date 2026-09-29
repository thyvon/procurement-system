<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('label');
            $table->string('phone', 32);
            $table->string('state', 32)->default('disconnected');
            $table->string('tdlib_base_url')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('flood_wait_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'phone']);
            $table->index(['entity_id', 'state']);
        });

        Schema::create('telegram_contacts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 32);
            $table->unsignedBigInteger('telegram_user_id')->nullable();
            $table->unsignedBigInteger('telegram_chat_id')->nullable();
            $table->foreignUlid('telegram_account_id')->nullable()->constrained('telegram_accounts')->nullOnDelete();
            $table->char('language', 2)->default('en');
            $table->string('supplier_code', 64)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_id', 'phone']);
            $table->unique('telegram_chat_id');
            $table->index(['entity_id', 'telegram_account_id']);
        });

        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->char('direction', 3);
            $table->foreignUlid('telegram_account_id')->constrained('telegram_accounts')->cascadeOnDelete();
            $table->foreignUlid('telegram_contact_id')->constrained('telegram_contacts')->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_chat_id')->nullable();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id')->nullable();
            $table->text('body');
            $table->string('file_path')->nullable();
            $table->string('status', 16);
            $table->string('idempotency_key', 128);
            $table->json('parsed_json')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('idempotency_key');
            $table->unique(
                ['telegram_account_id', 'telegram_chat_id', 'telegram_message_id'],
                'telegram_messages_tg_unique',
            );
            $table->index(['reference_type', 'reference_id']);
            $table->index(['telegram_contact_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
        Schema::dropIfExists('telegram_contacts');
        Schema::dropIfExists('telegram_accounts');
    }
};
