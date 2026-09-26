<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            // Cambodia operates in two currencies: USD and KHR. The exchange
            // rate is expressed as KHR per 1 USD (e.g. 4100); it snapshots the
            // rate the document was entered at so approval bands (USD-scale)
            // can convert the awarded total deterministically.
            $table->string('currency', 3)->default('USD')->after('status');
            $table->decimal('exchange_rate', 18, 6)->default(1)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate']);
        });
    }
};
