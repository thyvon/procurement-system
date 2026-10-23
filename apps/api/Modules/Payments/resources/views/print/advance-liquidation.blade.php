@use(App\Support\PrintFormatter)

@extends('print.layout')

@section('title', 'Liquidation of Advance · '.$liquidation['code'])

{{-- The official form is portrait A4; print.css ships the landscape @page
     default for the working sheets, so this sheet overrides it here (the
     renderer gets the same values via PdfBuilder::portrait/margins). --}}
@section('page-css')
<style>
    @page {
        size: A4 portrait;
        margin: 12mm;
    }
</style>
@endsection

@section('content')
@php
    $symbol = fn (string $currency): string => $currency === 'KHR' ? '៛' : '$';
    $amount = fn (float $value, string $currency): string => number_format($value, $currency === 'KHR' ? 0 : 2, '.', ',');
    $usd = fn (float $value): string => number_format($value, 2, '.', ',');
    // Official forms list a full block of ruled rows even when few lines
    // were entered — pad the body to ten rows.
    $blankRows = max(0, 10 - count($liquidation['lines']));
@endphp
<div class="flex min-h-[262mm] flex-col gap-3 text-xs text-foreground">
    <div class="flex items-end gap-4">
        <x-print.logo :src="$logo" />
        {{-- Bottom edge sits on the logo's underline: Logo ____ Title. --}}
        <h1 class="grow text-center">
            <span class="block font-muol">ការទូទាត់សាច់ប្រាក់ជាមុន</span>
            <span class="block text-base font-bold">Liquidation of Advance</span>
        </h1>
        {{-- Same footprint as the logo so the title stays optically centered
             across the whole form header. --}}
        <div aria-hidden class="h-14 w-28 shrink-0"></div>
    </div>

    <div class="flex justify-end">
        {{-- Grid: label left, ruled value in the next column — the three
             values share one starting edge, labels stay beside them. --}}
        <div class="self-start grid grid-cols-[auto_auto] items-end gap-x-3 gap-y-0.5">
            <span>លេខយោង / Ref:</span>
            <span class="w-40 border-b border-border font-mono font-medium">{{ $liquidation['code'] }}</span>
            <span>កាលបរិច្ឆេទ / Date:</span>
            <span class="w-40 border-b border-border font-medium">{{ PrintFormatter::dateOnly($liquidation['liquidationDate']) }}</span>
            <span>លេខយោងទូទាត់ / Advance Ref:</span>
            <span class="w-40 border-b border-border font-mono font-medium">{{ PrintFormatter::orDash($liquidation['cashRequestCode']) }}</span>
        </div>
    </div>

    <div class="flex items-end gap-x-6">
        <div class="flex items-end gap-1.5">
            <span class="shrink-0">អ្នកសុំទូទាត់ / Requester:</span>
            <span class="w-48 border-b border-border font-bold">{{ PrintFormatter::orDash($liquidation['createdBy']) }}</span>
        </div>
        <div class="flex items-end gap-1.5">
            <span class="shrink-0">តួនាទី / Position:</span>
            <span class="w-48 border-b border-border font-bold">{{ PrintFormatter::orDash($liquidation['createdByPosition']) }}</span>
        </div>
        <div class="flex items-end gap-1.5">
            <span class="shrink-0">អត្តលេខ / ID:</span>
            <span class="w-12 border-b border-border font-bold">{{ PrintFormatter::orDash($liquidation['createdById'] !== null ? (string) $liquidation['createdById'] : null) }}</span>
        </div>
    </div>

    <div class="space-y-1.5">
        <div class="flex flex-wrap items-end gap-x-5 gap-y-1">
            <div class="flex items-end gap-1.5">
                <span class="shrink-0">សាខា / Campus:</span>
                <span class="w-28 border-b border-border font-bold">{{ PrintFormatter::orDash($liquidation['campus']) }}</span>
            </div>
            <div class="flex items-end gap-1.5">
                <span class="shrink-0">អាជីវកម្ម / Division:</span>
                <span class="w-28 border-b border-border font-bold">{{ PrintFormatter::orDash($liquidation['division']) }}</span>
            </div>
            <div class="flex items-end gap-1.5">
                <span class="shrink-0">ផ្នែក / Department:</span>
                <span class="w-28 border-b border-border font-bold">{{ PrintFormatter::orDash($liquidation['department']) }}</span>
            </div>
        </div>
        <div class="flex items-end gap-2">
            <span class="shrink-0">អត្រាប្តូរប្រាក់ / Exchange Rate($):</span>
            <span class="w-28 border-b border-border font-bold">{{ PrintFormatter::numeric($liquidation['exchangeRate']) }}</span>
        </div>
    </div>

    <table class="w-full table-fixed border-collapse text-sm">
        <colgroup>
            <col class="w-14">
            <col>
            <col class="w-24">
            <col class="w-10">
            <col class="w-28">
        </colgroup>
        <thead class="text-xs">
            <tr>
                <th class="border border-border bg-muted/60 px-2 py-1 text-center font-medium">
                    <span class="block">ល.រ</span>
                    <span class="block">№</span>
                </th>
                <th class="border border-border bg-muted/60 px-2 py-1 text-center font-medium">
                    <span class="block">ពិពណ៌នា</span>
                    <span class="block">Description</span>
                </th>
                <th class="border border-border bg-muted/60 px-2 py-1 text-center font-medium">
                    <span class="block">រូបិយប័ណ្ណ</span>
                    <span class="block">Currency</span>
                </th>
                <th class="border border-border bg-muted/60 px-2 py-1 text-center font-medium" colspan="2">
                    <span class="block">ចំនួនទឹកប្រាក់</span>
                    <span class="block">Amount</span>
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach($liquidation['lines'] as $index => $line)
                <tr>
                    <td class="border border-border px-2 text-center align-top">{{ $index + 1 }}</td>
                    <td class="border border-border px-2 align-top">{{ $line['description'] }}</td>
                    <td class="border border-border px-2 align-top text-center">{{ $line['currency'] ?: 'USD' }}</td>
                    <td class="border-r-0 border border-border px-2 text-center align-top">{{ $symbol((string) ($line['currency'] ?: 'USD')) }}</td>
                    <td class="border-l-0 border border-border px-2 text-right align-top tabular-nums">{{ $amount((float) $line['amount'], (string) ($line['currency'] ?: 'USD')) }}</td>
                </tr>
            @endforeach
            {{-- One tall ruled blank area instead of a stack of rows: same
                 total height the multiple h-5 rows used to occupy. --}}
            @if($blankRows > 0)
                <tr>
                    <td class="border border-border px-2" style="height: {{ $blankRows * 20 }}px"></td>
                    <td class="border border-border px-2"></td>
                    <td class="border border-border px-2"></td>
                    <td class="border-r-0 border border-border px-2"></td>
                    <td class="border-l-0 border border-border px-2"></td>
                </tr>
            @endif
        </tbody>
        <tfoot class="text-xs font-bold">
            <tr>
                <td class="border border-border bg-muted/60 px-2 py-1 text-right" colspan="2">Total Actual Expense</td>
                <td class="border border-border bg-muted/60 px-2 py-1 text-center">In USD</td>
                <td class="border-r-0 border border-border bg-muted/60 px-2 py-1 text-center">{{ $symbol('USD') }}</td>
                <td class="border-l-0 border border-border bg-muted/60 px-2 py-1 text-right tabular-nums">{{ $usd((float) $liquidation['totalActualExpenseUsd']) }}</td>
            </tr>
            <tr>
                <td class="border border-border bg-muted/60 px-2 py-1 text-right" colspan="2">Total Cash Advance</td>
                <td class="border border-border bg-muted/60 px-2 py-1 text-center">In USD</td>
                <td class="border-r-0 border border-border bg-muted/60 px-2 py-1 text-center">{{ $symbol('USD') }}</td>
                <td class="border-l-0 border border-border bg-muted/60 px-2 py-1 text-right tabular-nums">{{ $usd((float) $liquidation['totalCashAdvanceUsd']) }}</td>
            </tr>
            <tr>
                <td class="border border-border bg-muted/60 px-2 py-1 text-right" colspan="2">Remaining Cash</td>
                <td class="border border-border bg-muted/60 px-2 py-1 text-center">In USD</td>
                <td class="border-r-0 border border-border bg-muted/60 px-2 py-1 text-center">{{ $symbol('USD') }}</td>
                <td class="border-l-0 border border-border bg-muted/60 px-2 py-1 text-right tabular-nums">{{ $usd((float) $liquidation['remainingCashUsd']) }}</td>
            </tr>
        </tfoot>
    </table>

    <div>
        <div>ចំណាំ / Remark:</div>
        <div class="border-b border-border">{{ ($liquidation['remark'] ?? '') !== '' ? $liquidation['remark'] : 'N/A' }}</div>
    </div>

    <x-print.signatures :columns="$columns" />
</div>
@endsection
