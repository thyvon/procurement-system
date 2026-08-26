<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Shared-kernel `users` keeps its framework bigint PK; business
            // tables use ULIDs. Revisit if users migrates to HasUlids.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->ulid('family_id')->index();
            $table->timestamp('expires_at');
            $table->timestamp('rotated_at')->nullable();
            $table->string('rotated_to', 26)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['family_id', 'revoked_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('entity_id', 26)->nullable()->after('id');
            $table->index('entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['entity_id']);
            $table->dropColumn('entity_id');
        });
    }
};
