@use(App\Support\PrintFormatter)

@extends('print.layout')

@section('title', 'Petty Cash Request Form · '.$cashRequest['code'])

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
    // Official forms list a full block of ruled rows even when few lines
    // were entered — pad the body to eight rows.
    $blankRows = max(0, 8 - count($cashRequest['lines']));
@endphp
<div class="flex min-h-[262mm] flex-col gap-3 text-xs text-foreground">
    <div class="flex items-end gap-4">
        @if($logo)
            <img src="{{ $logo }}" alt="" class="h-14 w-32 shrink-0 object-contain">
        @else
            <div aria-hidden class="h-14 w-32 shrink-0"></div>
        @endif
        {{-- Bottom edge sits on the logo's underline: Logo ____ Title. --}}
        <h1 class="grow text-center">
            <span class="block font-muol">ពាក្យស្នើសុំសាច់ប្រាក់បន្ទាប់បន្សំ</span>
            <span class="block text-base font-bold">Petty Cash Request Form</span>
        </h1>
        {{-- Same footprint as the logo so the title stays optically centered
             across the whole form header. --}}
        <div aria-hidden class="h-14 w-32 shrink-0"></div>
    </div>

    <div class="flex justify-end">
        {{-- Grid: label left, ruled value in the next column — both values
             share one starting edge, labels stay side by side with them. --}}
        <div class="self-start grid grid-cols-[auto_auto] items-end gap-x-3 gap-y-0.5">
            <span>លេខយោង / Ref:</span>
            <span class="w-40 border-b border-border font-mono font-medium">{{ $cashRequest['code'] }}</span>
            <span>កាលបរិច្ឆេទ / Date:</span>
            <span class="w-40 border-b border-border font-medium">{{ PrintFormatter::dateOnly($cashRequest['requestDate']) }}</span>
        </div>
    </div>

    <div class="flex items-end gap-x-6">
        <div class="flex items-end gap-1.5">
            <span class="shrink-0">អ្នកទទួល / Receiver:</span>
            <span class="w-48 border-b border-border font-bold">{{ PrintFormatter::orDash($cashRequest['createdBy']) }}</span>
        </div>
        <div class="flex items-end gap-1.5">
            <span class="shrink-0">តួនាទី / Position:</span>
            <span class="w-48 border-b border-border font-bold">{{ PrintFormatter::orDash($cashRequest['createdByPosition']) }}</span>
        </div>
        <div class="flex items-end gap-1.5">
            <span class="shrink-0">អត្តលេខ / ID:</span>
            <span class="w-12 border-b border-border font-bold">{{ PrintFormatter::orDash($cashRequest['createdById'] !== null ? (string) $cashRequest['createdById'] : null) }}</span>
        </div>
    </div>

    <div class="space-y-1.5">
        <div class="flex items-end gap-2">
            <span class="w-44 shrink-0">សាខា / Campus:</span>
            <span class="w-56 border-b border-border font-bold">{{ PrintFormatter::orDash($cashRequest['campus']) }}</span>
        </div>
        <div class="flex items-end gap-2">
            <span class="w-44 shrink-0">អាជីវកម្ម / Division:</span>
            <span class="w-56 border-b border-border font-bold">{{ PrintFormatter::orDash($cashRequest['division']) }}</span>
        </div>
        <div class="flex items-end gap-2">
            <span class="w-44 shrink-0">ផ្នែក / Department:</span>
            <span class="w-56 border-b border-border font-bold">{{ PrintFormatter::orDash($cashRequest['department']) }}</span>
        </div>
        <div class="flex items-end gap-2">
            <span class="w-44 shrink-0">អត្រាប្តូរប្រាក់ / Ex. Rate:</span>
            <span class="w-56 border-b border-border font-bold">{{ PrintFormatter::numeric($cashRequest['exchangeRate']) }}</span>
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
            @foreach($cashRequest['lines'] as $index => $line)
                <tr>
                    <td class="border border-border px-2 text-center align-top">{{ $index + 1 }}</td>
                    <td class="border border-border px-2 align-top">{{ $line['description'] }}</td>
                    <td class="border border-border px-2 align-top text-center">{{ $line['currency'] }}</td>
                    <td class="border-r-0 border border-border px-2 text-center align-top">{{ $symbol((string) $line['currency']) }}</td>
                    <td class="border-l-0 border border-border px-2 text-right align-top tabular-nums">{{ $amount((float) $line['amount'], (string) $line['currency']) }}</td>
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
                <td class="border border-border bg-muted/60 px-2 py-1 text-right" colspan="3">
                    <span class="block">សរុបជាដុល្លារ</span>
                    <span class="block">TOTAL IN USD</span>
                </td>
                <td class="border-r-0 border border-border bg-muted/60 px-2 py-1 text-center">{{ $symbol('USD') }}</td>
                <td class="border-l-0 border border-border bg-muted/60 px-2 py-1 text-right tabular-nums text-base">{{ number_format((float) $cashRequest['totalUsd'], 2, '.', ',') }}</td>
            </tr>
        </tfoot>
    </table>

    <div>
        <div>ចំណាំ / Remark:</div>
        <div class="border-b border-border">{{ ($cashRequest['remark'] ?? '') !== '' ? $cashRequest['remark'] : 'N/A' }}</div>
    </div>

    <x-print.signatures :columns="$columns" />
</div>
@endsection
