<?php

use App\Support\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('grand_total_usd', 18, 2)->default(0)->after('total');
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->decimal('grand_total_usd', 18, 2)->default(0)->after('line_total');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn('grand_total_usd');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('grand_total_usd');
        });
    }

    /**
     * Existing documents get their USD equivalent through the same helper the
     * service uses (Currency::toUsd) at the exchange rate recorded on the
     * invoice, so historical rows are dashboard-ready immediately.
     */
    private function backfill(): void
    {
        DB::table('invoices')->whereNull('deleted_at')->chunkById(100, function ($invoices): void {
            /** @var array<string, array{0: string, 1: float}> $rates invoice id => [currency, rate] */
            $rates = [];

            foreach ($invoices as $invoice) {
                $currency = (string) $invoice->currency;
                $rate = (float) $invoice->exchange_rate;
                $rates[$invoice->id] = [$currency, $rate];

                DB::table('invoices')->where('id', $invoice->id)->update([
                    'grand_total_usd' => Currency::toUsd((float) $invoice->total, $currency, $rate),
                ]);
            }

            DB::table('invoice_lines')
                ->whereIn('invoice_id', array_keys($rates))
                ->get()
                ->each(function ($line) use ($rates): void {
                    [$currency, $rate] = $rates[$line->invoice_id];

                    DB::table('invoice_lines')->where('id', $line->id)->update([
                        'grand_total_usd' => Currency::toUsd((float) $line->line_total, $currency, $rate),
                    ]);
                });
        });
    }
};
