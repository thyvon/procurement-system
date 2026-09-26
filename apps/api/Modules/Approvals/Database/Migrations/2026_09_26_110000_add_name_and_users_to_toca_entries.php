<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toca_entries', function (Blueprint $table): void {
            $table->string('name')->nullable();
        });

        // Backfill a readable label from the band before locking the column.
        foreach (DB::table('toca_entries')->get() as $row) {
            $upper = $row->max_amount === null ? '+' : " – {$row->max_amount}";

            DB::table('toca_entries')
                ->where('id', $row->id)
                ->update(['name' => "{$row->subject_type} {$row->min_amount}{$upper}"]);
        }

        Schema::table('toca_entries', function (Blueprint $table): void {
            $table->string('name')->nullable(false)->change();
        });

        Schema::create('toca_entry_user', function (Blueprint $table): void {
            $table->foreignUlid('toca_entry_id')->constrained('toca_entries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['toca_entry_id', 'user_id']);
            $table->index('user_id');
        });

        foreach (DB::table('toca_entries')->get() as $row) {
            DB::table('toca_entry_user')->insert([
                'toca_entry_id' => $row->id,
                'user_id' => $row->user_id,
            ]);
        }

        Schema::table('toca_entries', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'subject_type']);
            $table->dropColumn('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('toca_entries', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        foreach (DB::table('toca_entry_user')->get() as $row) {
            DB::table('toca_entries')
                ->where('id', $row->toca_entry_id)
                ->update(['user_id' => $row->user_id]);
        }

        Schema::dropIfExists('toca_entry_user');

        Schema::table('toca_entries', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }
};
