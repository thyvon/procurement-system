<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'position')) {
            return;
        }

        $today = now()->toDateString();

        DB::table('users')
            ->select(['id', 'entity_id', 'position'])
            ->whereNotNull('position')
            ->where('position', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($today): void {
                $now = now();

                $rows = $users->map(fn ($user): array => [
                    'id' => (string) Str::ulid(),
                    'entity_id' => $user->entity_id,
                    'user_id' => $user->id,
                    'card_id' => null,
                    'position' => $user->position,
                    'campus_id' => null,
                    'division_id' => null,
                    'department_id' => null,
                    'effective_from' => $today,
                    'effective_to' => null,
                    'is_current' => true,
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('employee_profiles')->insert($rows);
            });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'position')) {
            return;
        }

        DB::table('employee_profiles')
            ->select(['id', 'user_id', 'position'])
            ->where('is_current', true)
            ->whereNull('deleted_at')
            ->whereNotNull('position')
            ->orderBy('id')
            ->chunkById(500, function ($profiles): void {
                foreach ($profiles as $profile) {
                    DB::table('users')
                        ->where('id', $profile->user_id)
                        ->update(['position' => $profile->position]);
                }
            });
    }
};
