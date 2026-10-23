@use(App\Support\PrintFormatter)

@extends('print.layout')

@section('title', 'Petty Cash Clearance · '.$clearance['code'])

@section('content')
@php
    $head = 'border border-border bg-muted/60 px-1 py-1 text-left font-medium';
    $headRight = 'border border-border bg-muted/60 px-1 py-1 text-right font-medium';
    $td = 'border border-border px-1 py-1';
@endphp

<div class="flex min-h-[186mm] flex-col gap-3 text-xs text-foreground">
    <div class="grid grid-cols-[1fr_auto_1fr] items-start gap-4">
        <x-print.logo :src="$logo" />
        <h1 class="place-self-center text-center text-lg font-bold tracking-tight">
            <span class="block">ការទូទាត់សាច់ប្រាក់</span>
            <span class="block">Petty Cash Clearance</span>
        </h1>
        {{-- Official-form meta block: bilingual label left, ruled value right —
             Ref, Date, Ref (Request) and the exchange rate, exactly the four
             rows of the paper form. Amounts live in the totals box below. --}}
        <div class="justify-self-end self-start grid grid-cols-[auto_auto] items-end gap-x-3 gap-y-0.5 text-left">
            <span>លេខយោង / Ref:</span>
            <span class="w-44 border-b border-border font-mono font-medium">{{ $clearance['code'] }}</span>
            <span>ថ្ងៃទី / Date:</span>
            <span class="w-44 border-b border-border font-medium">{{ PrintFormatter::orDash(PrintFormatter::dateOnly($clearance['clearanceDate'])) }}</span>
            <span>លេខយោង / Ref (Request):</span>
            <span class="w-44 border-b border-border font-mono font-medium">{{ PrintFormatter::orDash($clearance['cashRequestCode']) }}</span>
            <span>អត្រាប្តូរប្រាក់ ($) / Exchange rate ($):</span>
            <span class="w-44 border-b border-border font-medium">{{ PrintFormatter::numeric($clearance['exchangeRate']) }}</span>
        </div>
    </div>

    <table class="w-full border-collapse">
        <thead>
            {{-- English only, as on the paper form: the bilingual block above
                 (title, meta) and the approval titles carry the Khmer. --}}
            <tr>
                <th class="{{ $head }} text-center">No.</th>
                <th class="{{ $head }}">Invoice date</th>
                <th class="{{ $head }}">PR ref</th>
                <th class="{{ $head }}">Purchaser</th>
                <th class="{{ $head }}">Description</th>
                <th class="{{ $head }}">Vendor</th>
                <th class="{{ $head }}">Vendor phone no.</th>
                <th class="{{ $head }}">Campus</th>
                <th class="{{ $head }}">Division</th>
                <th class="{{ $head }}">Department</th>
                <th class="{{ $headRight }}">Qty</th>
                <th class="{{ $head }}">UoM</th>
                <th class="{{ $headRight }}">Unit price</th>
                <th class="{{ $headRight }}">Discount</th>
                <th class="{{ $headRight }}">VAT</th>
                <th class="{{ $headRight }}">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($clearance['lines'] as $index => $line)
                <tr>
                    <td class="{{ $td }} text-center tabular-nums">{{ $index + 1 }}</td>
                    <td class="{{ $td }}">{{ PrintFormatter::orDash(PrintFormatter::dateOnly($line['invoiceDate'])) }}</td>
                    <td class="{{ $td }}">{{ PrintFormatter::orDash($line['prRef']) }}</td>
                    <td class="{{ $td }}">{{ PrintFormatter::orDash($line['purchaser']) }}</td>
                    <td class="{{ $td }}">{{ $line['description'] }}</td>
                    <td class="{{ $td }}">{{ $line['vendor'] }}</td>
                    <td class="{{ $td }}">{{ PrintFormatter::orDash($line['vendorPhone']) }}</td>
                    <td class="{{ $td }}">{{ $line['campus'] }}</td>
                    <td class="{{ $td }}">{{ $line['division'] }}</td>
                    <td class="{{ $td }}">{{ $line['department'] }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ PrintFormatter::numeric($line['qty']) }}</td>
                    <td class="{{ $td }}">{{ $line['uom'] }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ PrintFormatter::money($line['unitPriceUsd'], 'USD') }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ PrintFormatter::money($line['discountUsd'], 'USD') }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ PrintFormatter::money($line['vatUsd'], 'USD') }}</td>
                    <td class="{{ $td }} text-right font-semibold tabular-nums">{{ PrintFormatter::money($line['amountUsd'], 'USD') }}</td>
                </tr>
            @endforeach
        </tbody>
        {{-- The paper form's three money rows are part of this same grid: the
             label spans every column up to Amount, so each value sits exactly
             under its Amount column — one table, one border box. --}}
        <tfoot>
            <tr>
                <td class="{{ $td }}" colspan="15">
                    <span class="float-right font-semibold">Total</span>
                </td>
                <td class="{{ $td }} text-right font-semibold tabular-nums">{{ PrintFormatter::money($clearance['totalUsd'], 'USD') }}</td>
            </tr>
            <tr>
                <td class="{{ $td }}" colspan="15">
                    <span class="float-right font-semibold">Request amount</span>
                </td>
                <td class="{{ $td }} text-right font-semibold tabular-nums">{{ PrintFormatter::money($clearance['requestAmountUsd'], 'USD') }}</td>
            </tr>
            <tr>
                <td class="{{ $td }}" colspan="15">
                    <span class="float-right font-semibold">Balance</span>
                </td>
                <td class="{{ $td }} text-right font-semibold tabular-nums">{{ PrintFormatter::money($clearance['balanceUsd'], 'USD') }}</td>
            </tr>
        </tfoot>
    </table>

    <x-print.signatures :columns="$columns" />
</div>
@endsection
