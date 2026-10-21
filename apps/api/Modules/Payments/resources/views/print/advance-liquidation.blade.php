@use(App\Support\PrintFormatter)

@extends('print.layout')

@section('title', 'Advance Liquidation · '.$liquidation['code'])

@section('content')
@php
    $head = 'border border-border bg-muted/60 px-1 py-1 text-left font-medium';
    $headCenter = 'border border-border bg-muted/60 px-1 py-1 text-center font-medium';
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
            <span class="block">ការទូទាត់ប្រាក់ជាមុន</span>
            <span class="block">ADVANCE LIQUIDATION</span>
        </h1>
        <div class="justify-self-end text-left leading-relaxed">
            <div>កូដ / Code: <span class="font-mono">{{ $liquidation['code'] }}</span></div>
            <div>ថ្ងៃទូទាត់ជាមុន / Liquidation date: {{ PrintFormatter::orDash(PrintFormatter::dateOnly($liquidation['liquidationDate'])) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-x-8 gap-y-1 border border-border px-2 py-1.5 md:grid-cols-3">
        <x-print.meta-cell label-km="សំណើសាច់ប្រាក់" label-en="Cash request" :value="$liquidation['cashRequestCode']" />
        <x-print.meta-cell label-km="អត្រាប្តូរប្រាក់" label-en="Exchange rate" :value="PrintFormatter::numeric($liquidation['exchangeRate'])" />
        <x-print.meta-cell label-km="សាខា" label-en="Campus" :value="$liquidation['campus']" />
        <x-print.meta-cell label-km="ផ្នែក" label-en="Division" :value="$liquidation['division']" />
        <x-print.meta-cell label-km="នាយកដ្ឋាន" label-en="Department" :value="$liquidation['department']" />
        <x-print.meta-cell label-km="សរុបប្រាក់ជាមុន" label-en="Total cash advance" :value="PrintFormatter::money($liquidation['totalCashAdvanceUsd'], 'USD')" />
        <x-print.meta-cell label-km="ចំណាយពិតសរុប" label-en="Total actual expense" :value="PrintFormatter::money($liquidation['totalActualExpenseUsd'], 'USD')" />
        <x-print.meta-cell label-km="សាច់ប្រាក់នៅសល់" label-en="Remaining cash" :value="PrintFormatter::money($liquidation['remainingCashUsd'], 'USD')" />
    </div>

    <table class="w-full border-collapse text-[10px]">
        <thead>
            <tr>
                <th class="w-8 {{ $headCenter }}">#</th>
                <th class="{{ $head }}"><span class="block">ការពិពណ៌នា</span><span class="block">Description</span></th>
                <th class="w-24 {{ $head }}"><span class="block">រូបិយប័ណ្ណ</span><span class="block">Currency</span></th>
                <th class="w-32 {{ $headRight }}"><span class="block">ចំនួនទឹកប្រាក់</span><span class="block">Amount</span></th>
                <th class="w-32 {{ $headRight }}"><span class="block">ចំនួនទឹកប្រាក់ (USD)</span><span class="block">Amount (USD)</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach($liquidation['lines'] as $index => $line)
                <tr>
                    <td class="{{ $td }} text-center tabular-nums">{{ $index + 1 }}</td>
                    <td class="{{ $td }}">{{ $line['description'] }}</td>
                    <td class="{{ $td }}">{{ $line['currency'] ?: 'USD' }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ PrintFormatter::money($line['amount'], $line['currency'] ?: 'USD') }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ PrintFormatter::money($line['amountUsd'], 'USD') }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="{{ $td }}" colspan="4">
                    <span class="float-right font-semibold">ចំណាយពិតសរុប / Total actual expense:</span>
                </td>
                <td class="{{ $td }} text-right font-semibold tabular-nums">{{ PrintFormatter::money($liquidation['totalActualExpenseUsd'], 'USD') }}</td>
            </tr>
        </tbody>
    </table>

    <x-print.signatures
        :columns="$columns"
        :labels="['name' => 'ឈ្មោះ / Name', 'position' => 'តួនាទី / Position', 'date' => 'កាលបរិច្ឆេទ / Date']"
    />
</div>
@endsection
