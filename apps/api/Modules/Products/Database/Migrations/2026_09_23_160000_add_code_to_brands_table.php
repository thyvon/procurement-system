<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Products\Models\Brand;
use Modules\Products\Services\CodeGenerationService;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->after('entity_id');
            $table->unique(['entity_id', 'code']);
        });

        $codes = app(CodeGenerationService::class);

        DB::table('brands')->whereNull('code')->orderBy('created_at')->each(function ($brand) use ($codes) {
            DB::table('brands')
                ->where('id', $brand->id)
                ->update([
                    'code' => $codes->next('BRD', Brand::class, $brand->entity_id),
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropUnique(['entity_id', 'code']);
            $table->dropColumn('code');
        });
    }
};
