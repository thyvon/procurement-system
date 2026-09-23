<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Products\Models\Uom;
use Modules\Products\Services\CodeGenerationService;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uoms', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->after('entity_id');
            $table->unique(['entity_id', 'code']);
        });

        $codes = app(CodeGenerationService::class);

        DB::table('uoms')->whereNull('code')->orderBy('created_at')->each(function ($uom) use ($codes) {
            DB::table('uoms')
                ->where('id', $uom->id)
                ->update([
                    'code' => $codes->next('UOM', Uom::class, $uom->entity_id),
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('uoms', function (Blueprint $table) {
            $table->dropUnique(['entity_id', 'code']);
            $table->dropColumn('code');
        });
    }
};
