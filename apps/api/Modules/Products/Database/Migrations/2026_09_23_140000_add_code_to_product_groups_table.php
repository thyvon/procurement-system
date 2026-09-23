<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Products\Models\ProductGroup;
use Modules\Products\Services\CodeGenerationService;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_groups', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->after('entity_id');
            $table->unique(['entity_id', 'code']);
        });

        $codes = app(CodeGenerationService::class);

        DB::table('product_groups')->whereNull('code')->orderBy('created_at')->each(function ($group) use ($codes) {
            DB::table('product_groups')
                ->where('id', $group->id)
                ->update([
                    'code' => $codes->next('GRP', ProductGroup::class, $group->entity_id),
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('product_groups', function (Blueprint $table) {
            $table->dropUnique(['entity_id', 'code']);
            $table->dropColumn('code');
        });
    }
};
