@use(App\Support\PrintFormatter)

@extends('print.layout')

@section('title', 'Cash Clearance · '.$clearance['code'])

@section('content')
@php
    $head = 'border border-border bg-muted/60 px-1 py-1 text-left font-medium';
    $headRight = 'border border-border bg-muted/60 px-1 py-1 text-right font-medium';
    $td = 'border border-border px-1 py-1';
@endphp

<div class="flex min-h-[186mm] flex-col gap-3 text-xs text-foreground">
    <div class="grid grid-cols-[1fr_auto_1fr] items-start gap-4">
        @if($logo)
            <img src="{{ $logo }}" alt="" class="h-14 w-28 shrink-0 object-contain">
        @else
            <div aria-hidden class="h-14 w-28 shrink-0"></div>
        @endif
        <h1 class="place-self-center text-center text-lg font-bold tracking-tight">
            <span class="block">ការទូទាត់សាច់ប្រាក់</span>
            <span class="block">CASH CLEARANCE</span>
        </h1>
        <div class="justify-self-end text-left leading-relaxed">
            <div>កូដ / Code: <span class="font-mono">{{ $clearance['code'] }}</span></div>
            <div>ថ្ងៃទូទាត់ / Clearance date: {{ PrintFormatter::orDash(PrintFormatter::dateOnly($clearance['clearanceDate'])) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-x-8 gap-y-1 border border-border px-2 py-1.5 md:grid-cols-3">
        <x-print.meta-cell label-km="សំណើសាច់ប្រាក់" label-en="Cash request" :value="$clearance['cashRequestCode']" />
        <x-print.meta-cell label-km="អត្រាប្តូរប្រាក់" label-en="Exchange rate" :value="PrintFormatter::numeric($clearance['exchangeRate'])" />
        <x-print.meta-cell label-km="ចំនួនស្នើសុំ" label-en="Request amount" :value="PrintFormatter::money($clearance['requestAmountUsd'], 'USD')" />
        <x-print.meta-cell label-km="សរុប (USD)" label-en="Total (USD)" :value="PrintFormatter::money($clearance['totalUsd'], 'USD')" />
        <x-print.meta-cell label-km="សមតុល្យ" label-en="Balance" :value="PrintFormatter::money($clearance['balanceUsd'], 'USD')" />
    </div>

    <table class="w-full border-collapse text-[10px]">
        <thead>
            <tr>
                <th class="{{ $head }}">#</th>
                <th class="{{ $head }}"><span class="block">ថ្ងៃវិក្កយបត្រ</span><span class="block">Invoice date</span></th>
                <th class="{{ $head }}"><span class="block">លេខយោង PR</span><span class="block">PR ref</span></th>
                <th class="{{ $head }}"><span class="block">អ្នកទិញ</span><span class="block">Purchaser</span></th>
                <th class="{{ $head }}"><span class="block">ការពិពណ៌នា</span><span class="block">Description</span></th>
                <th class="{{ $head }}"><span class="block">អ្នកផ្គត់ផ្គង់</span><span class="block">Vendor</span></th>
                <th class="{{ $head }}"><span class="block">សាខា</span><span class="block">Campus</span></th>
                <th class="{{ $head }}"><span class="block">ផ្នែក</span><span class="block">Division</span></th>
                <th class="{{ $head }}"><span class="block">នាយកដ្ឋាន</span><span class="block">Department</span></th>
                <th class="{{ $headRight }}"><span class="block">ចំនួន</span><span class="block">Qty</span></th>
                <th class="{{ $head }}"><span class="block">ឯកតា</span><span class="block">UoM</span></th>
                <th class="{{ $headRight }}"><span class="block">តម្លៃឯកតា</span><span class="block">Unit price</span></th>
                <th class="{{ $headRight }}"><span class="block">បញ្ចុះតម្លៃ</span><span class="block">Discount</span></th>
                <th class="{{ $headRight }}"><span class="block">អាករ</span><span class="block">VAT</span></th>
                <th class="{{ $headRight }}"><span class="block">ចំនួនទឹកប្រាក់</span><span class="block">Amount</span></th>
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
            <tr>
                <td class="{{ $td }}" colspan="14">
                    <span class="float-right font-semibold">សរុប (USD) / Total (USD):</span>
                </td>
                <td class="{{ $td }} text-right font-semibold tabular-nums">{{ PrintFormatter::money($clearance['totalUsd'], 'USD') }}</td>
            </tr>
        </tbody>
    </table>

    <x-print.signatures
        :columns="$columns"
        :labels="['name' => 'ឈ្មោះ / Name', 'position' => 'តួនាទី / Position', 'date' => 'កាលបរិច្ឆេទ / Date']"
    />
</div>
@endsection
