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
    <div class="flex items-start justify-between gap-4">
        @if($logo)
            <img src="{{ $logo }}" alt="" class="h-14 w-32 shrink-0 object-contain">
        @else
            <div aria-hidden class="h-14 w-32 shrink-0"></div>
        @endif
        <h1 class="grow place-self-center text-center">
            <span class="block">ពាក្យសុំប្រាក់ជំនួយការចាយវាយ</span>
            <span class="block text-base font-bold">Petty Cash Request Form</span>
        </h1>
        <div class="shrink-0 self-start border border-border">
            <div class="flex items-center gap-3 border-b border-border px-2 py-0.5">
                <span class="shrink-0">លេខយោង / Ref:</span>
                <span class="font-mono font-medium">{{ $cashRequest['code'] }}</span>
            </div>
            <div class="flex items-center gap-3 px-2 py-0.5">
                <span class="shrink-0">ថ្ងៃទី / Date:</span>
                <span class="font-medium">{{ PrintFormatter::dateOnly($cashRequest['requestDate']) }}</span>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-end gap-x-10 gap-y-1">
        <div class="flex items-end gap-1.5">
            <span class="shrink-0">អ្នកសុំច្បាប់ / Receiver:</span>
            <span class="w-40 border-b border-border font-medium">{{ PrintFormatter::orDash($cashRequest['createdBy']) }}</span>
        </div>
        <div class="flex items-end gap-1.5">
            <span class="shrink-0">តួនាទី / Position:</span>
            <span class="w-48 border-b border-border font-medium">{{ PrintFormatter::orDash($cashRequest['createdByPosition']) }}</span>
        </div>
        <div class="flex items-end gap-1.5">
            <span class="shrink-0">អត្តលេខ / ID:</span>
            <span class="w-24 border-b border-border font-medium">{{ PrintFormatter::orDash($cashRequest['createdById'] !== null ? (string) $cashRequest['createdById'] : null) }}</span>
        </div>
    </div>

    <div class="space-y-1.5">
        <div class="flex items-end gap-2">
            <span class="w-44 shrink-0">ថ្នាក់ / Campus:</span>
            <span class="w-56 border-b border-border font-medium">{{ PrintFormatter::orDash($cashRequest['campus']) }}</span>
        </div>
        <div class="flex items-end gap-2">
            <span class="w-44 shrink-0">នាយកដ្ឋាន / Division:</span>
            <span class="w-56 border-b border-border font-medium">{{ PrintFormatter::orDash($cashRequest['division']) }}</span>
        </div>
        <div class="flex items-end gap-2">
            <span class="w-44 shrink-0">ផ្នែក / Department:</span>
            <span class="w-56 border-b border-border font-medium">{{ PrintFormatter::orDash($cashRequest['department']) }}</span>
        </div>
        <div class="flex items-end gap-2">
            <span class="w-44 shrink-0">អត្រាប្តូរប្រាក់ / Ex. Rate($):</span>
            <span class="w-56 border-b border-border font-medium">{{ PrintFormatter::numeric($cashRequest['exchangeRate']) }}</span>
        </div>
    </div>

    <table class="w-full table-fixed border-collapse text-xs">
        <colgroup>
            <col class="w-10">
            <col>
            <col class="w-24">
            <col class="w-10">
            <col class="w-28">
        </colgroup>
        <thead>
            <tr>
                <th class="border border-border bg-muted/60 px-2 py-1 text-left font-medium">ល.រ / №</th>
                <th class="border border-border bg-muted/60 px-2 py-1 text-left font-medium">ពិពណ៌នា / Description</th>
                <th class="border border-border bg-muted/60 px-2 py-1 text-left font-medium">រូបិយប័ណ្ណ / Currency</th>
                <th class="border border-border bg-muted/60 px-2 py-1 text-right font-medium" colspan="2">ចំនួនទឹកប្រាក់ / Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cashRequest['lines'] as $index => $line)
                <tr>
                    <td class="border border-border px-2 text-center align-top">{{ $index + 1 }}</td>
                    <td class="border border-border px-2 align-top">{{ $line['description'] }}</td>
                    <td class="border border-border px-2 align-top">{{ $line['currency'] }}</td>
                    <td class="border border-border px-2 text-center align-top">{{ $symbol((string) $line['currency']) }}</td>
                    <td class="border border-border px-2 text-right align-top tabular-nums">{{ $amount((float) $line['amount'], (string) $line['currency']) }}</td>
                </tr>
            @endforeach
            @for($row = 0; $row < $blankRows; $row++)
                <tr>
                    <td class="h-6 border border-border px-2"></td>
                    <td class="h-6 border border-border px-2"></td>
                    <td class="h-6 border border-border px-2"></td>
                    <td class="h-6 border border-border px-2"></td>
                    <td class="h-6 border border-border px-2"></td>
                </tr>
            @endfor
        </tbody>
        <tfoot>
            <tr>
                <td class="border border-border bg-muted/60 px-2 py-1 text-right font-medium" colspan="3">Total in USD</td>
                <td class="border border-border bg-muted/60 px-2 py-1 text-center font-medium">{{ $symbol('USD') }}</td>
                <td class="border border-border bg-muted/60 px-2 py-1 text-right font-medium tabular-nums">{{ number_format((float) $cashRequest['totalUsd'], 2, '.', ',') }}</td>
            </tr>
        </tfoot>
    </table>

    <div>
        <div>ចំណាំ / Remark:</div>
        <div class="border-b border-border">{{ ($cashRequest['remark'] ?? '') !== '' ? $cashRequest['remark'] : 'N/A' }}</div>
    </div>

    <x-print.signatures
        :columns="$columns"
        :labels="['name' => 'ឈ្មោះ / Name', 'position' => 'តួនាទី / Position']"
        :fields="['name', 'position']"
    />
</div>
@endsection
