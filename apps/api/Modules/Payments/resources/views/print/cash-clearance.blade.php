@use(App\Support\PrintFormatter)

@extends('print.layout')

@section('title', 'Petty Cash Clearance · '.$clearance['code'])

@section('content')
@php
    $head = 'border border-border bg-muted/60 px-1 py-1 text-center font-medium';
    $td = 'border border-border px-1 py-1';
@endphp

<div class="flex min-h-[186mm] flex-col gap-2 text-xs text-foreground">
    <div class="grid grid-cols-[1fr_auto_1fr] items-start gap-4">
        <x-print.logo :src="$logo" />
        <h1 class="place-self-center text-center">
            <span class="block font-muol">ពាក្យស្នើសុំទូទាត់ប្រាក់បន្ទាប់បន្សំ</span>
            <span class="block text-base font-bold">Petty Cash Clearance</span>
        </h1>
        {{-- Official-form meta block: bilingual label left, ruled value right —
             Ref, Date, Ref (Request) and the exchange rate, exactly the four
             rows of the paper form. Amounts live in the totals box below. --}}
        <div class="justify-self-end self-start grid grid-cols-[auto_auto] items-end gap-x-3 gap-y-0.5 text-left">
            <span>លេខយោង / Ref:</span>
            <span class="w-44 border-b border-border font-mono font-medium">{{ $clearance['code'] }}</span>
            <span>កាលបរិច្ឆេទ / Date:</span>
            <span class="w-44 border-b border-border font-medium">{{ PrintFormatter::orDash(PrintFormatter::dateOnly($clearance['clearanceDate'])) }}</span>
            <span>លេខយោង / Ref (Request):</span>
            <span class="w-44 border-b border-border font-mono font-medium">{{ PrintFormatter::orDash($clearance['cashRequestCode']) }}</span>
            <span>អត្រាប្តូរប្រាក់ / Exchange rate ($):</span>
            <span class="w-44 border-b border-border font-medium">{{ PrintFormatter::numeric($clearance['exchangeRate']) }}</span>
        </div>
    </div>

    <table class="clearance-lines w-full table-fixed border-collapse">
        <colgroup>
            <col class="w-[3%]">
            <col class="w-[6%]">
            <col class="w-[10.5%]">
            <col class="w-[6%]">
            <col class="w-[17%]">
            <col class="w-[8%]">
            <col class="w-[6.5%]">
            <col class="w-[4.5%]">
            <col class="w-[5%]">
            <col class="w-[4%]">
            <col class="w-[4%]">
            <col class="w-[4%]">
            <col class="w-[5%]">
            <col class="w-[5%]">
            <col class="w-[5%]">
            <col class="w-[6.5%]">
        </colgroup>
        <thead class="text-[10px]">
            {{-- English only, as on the paper form: the bilingual block above
                 (title, meta) and the approval titles carry the Khmer. --}}
            <tr>
                <th class="{{ $head }}">No.</th>
                <th class="{{ $head }}">Invoice date</th>
                <th class="{{ $head }}">PR ref</th>
                <th class="{{ $head }}">Purchaser</th>
                <th class="{{ $head }}">Description</th>
                <th class="{{ $head }}">Vendor</th>
                <th class="{{ $head }}">Vendor phone no.</th>
                <th class="{{ $head }} whitespace-nowrap">Campus</th>
                <th class="{{ $head }} whitespace-nowrap">Division</th>
                <th class="{{ $head }} whitespace-nowrap">Dept.</th>
                <th class="{{ $head }}">Qty</th>
                <th class="{{ $head }}">UoM</th>
                <th class="{{ $head }}">Unit price</th>
                <th class="{{ $head }}">Discount</th>
                <th class="{{ $head }}">VAT</th>
                <th class="{{ $head }}">Amount</th>
            </tr>
        </thead>
        <tbody class="text-[10px] leading-tight">
            @foreach($clearance['lines'] as $index => $line)
                <tr>
                    <td class="{{ $td }} text-center tabular-nums">{{ $index + 1 }}</td>
                    <td class="{{ $td }} whitespace-nowrap text-center">{{ PrintFormatter::orDash(PrintFormatter::dateOnly($line['invoiceDate'])) }}</td>
                    <td class="{{ $td }} whitespace-nowrap text-center">{{ PrintFormatter::orDash($line['prRef']) }}</td>
                    <td class="{{ $td }} text-center">{{ PrintFormatter::orDash($line['purchaser']) }}</td>
                    <td class="{{ $td }}">{{ $line['description'] }}</td>
                    <td class="{{ $td }} text-center">{{ $line['vendor'] }}</td>
                    <td class="{{ $td }} text-center">{{ PrintFormatter::orDash($line['vendorPhone']) }}</td>
                    <td class="{{ $td }} text-center">{{ $line['campus'] }}</td>
                    <td class="{{ $td }} text-center">{{ $line['division'] }}</td>
                    <td class="{{ $td }} text-center">{{ $line['department'] }}</td>
                    <td class="{{ $td }} text-center tabular-nums">{{ PrintFormatter::numeric($line['qty']) }}</td>
                    <td class="{{ $td }} text-center">{{ $line['uom'] }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ PrintFormatter::money($line['unitPriceUsd'], 'USD') }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ PrintFormatter::money($line['discountUsd'], 'USD') }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ PrintFormatter::money($line['vatUsd'], 'USD') }}</td>
                    <td class="{{ $td }} text-right font-semibold tabular-nums">{{ PrintFormatter::money($line['amountUsd'], 'USD') }}</td>
                </tr>
            @endforeach
        </tbody>
        {{-- The paper form's three money rows are part of this same grid: the
             columns No. to Unit price merge into one blank cell spanning all
             three rows, each label sits over the merged Discount + VAT cells,
             the value stays in the Amount column, and the rows are shaded. --}}
        <tfoot>
            <tr>
                <td class="{{ $td }} bg-muted/60" colspan="13" rowspan="3"></td>
                <td class="{{ $td }} bg-muted/60 text-left font-semibold" colspan="2">Total</td>
                <td class="{{ $td }} bg-muted/60 text-right font-semibold tabular-nums">{{ PrintFormatter::money($clearance['totalUsd'], 'USD') }}</td>
            </tr>
            <tr>
                <td class="{{ $td }} bg-muted/60 text-left font-semibold" colspan="2">Request amount</td>
                <td class="{{ $td }} bg-muted/60 text-right font-semibold tabular-nums">{{ PrintFormatter::money($clearance['requestAmountUsd'], 'USD') }}</td>
            </tr>
            <tr>
                <td class="{{ $td }} bg-muted/60 text-left font-semibold" colspan="2">Balance</td>
                <td class="{{ $td }} bg-muted/60 text-right font-semibold tabular-nums">{{ PrintFormatter::money($clearance['balanceUsd'], 'USD') }}</td>
            </tr>
        </tfoot>
    </table>

    <x-print.signatures :columns="$columns" />
</div>
@endsection

@section('page-css')
<style>
    .print-area .clearance-lines td {
        padding: 2px 4px !important;
        overflow-wrap: break-word;
    }
</style>
@endsection
