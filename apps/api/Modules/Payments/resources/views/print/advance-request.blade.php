@use(App\Support\PrintFormatter)

@extends('print.layout')

@section('title', 'Advance Request · '.$cashRequest['code'])

@section('content')
<div class="flex min-h-[186mm] flex-col gap-3 text-xs text-foreground">
    <div class="grid grid-cols-[1fr_auto_1fr] items-start gap-4">
        @if($logo)
            <img src="{{ $logo }}" alt="" class="h-14 w-28 shrink-0 object-contain">
        @else
            <div aria-hidden class="h-14 w-28 shrink-0"></div>
        @endif
        <h1 class="place-self-center text-center text-lg font-bold tracking-tight">
            <span class="block">សំណើសុំប្រាក់</span>
            <span class="block text-sm font-semibold">Cash Request</span>
        </h1>
        <div class="justify-self-end text-left leading-relaxed">
            <div>Code · លេខកូដ: <span class="font-mono">{{ $cashRequest['code'] }}</span></div>
            <div>Status · ស្ថានភាព: {{ $cashRequest['status'] ?? '' }}</div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-x-8 gap-y-1 border border-border px-2 py-1.5 md:grid-cols-3">
        <x-print.info-row label="Type · ប្រភេទ" value="Advance · លុយទុកមុន" />
        <x-print.info-row label="Campus · សាខា" :value="$cashRequest['campus']" />
        <x-print.info-row label="Division · ផ្នែក" :value="$cashRequest['division']" />
        <x-print.info-row label="Department · នាយកដ្ឋាន" :value="$cashRequest['department']" />
        <x-print.info-row label="Request date · ថ្ងៃសុំ" :value="PrintFormatter::date($cashRequest['requestDate'])" />
        <x-print.info-row label="Exchange rate · អត្រាប្តូរប្រាក់" :value="PrintFormatter::numeric($cashRequest['exchangeRate'])" />
        <x-print.info-row label="Total (USD) · សរុប (ដុល្លារ)" :value="PrintFormatter::money($cashRequest['totalUsd'], 'USD')" />
        <x-print.info-row label="Mode of payment · របៀបទូទាត់" :value="$cashRequest['modeOfPayment']" />
        <x-print.info-row label="Payment to · ទូទាត់ឲ្យ" :value="$cashRequest['paymentTo']" />
        <x-print.info-row label="Bank account no · លេខគណនីធនាគារ" :value="$cashRequest['bankAccountNo']" />
        <x-print.info-row label="Bank name · ធនាគារ" :value="$cashRequest['bankName']" />
        <x-print.info-row label="Adv. request for · សុំលុយជូន" :value="$cashRequest['advanceRequestFor']" />
        <x-print.info-row label="Created by · អ្នកបង្កើត" :value="$cashRequest['createdBy']" />
        <x-print.info-row label="Position · តួនាទី" :value="$cashRequest['createdByPosition']" />
        <x-print.info-row label="Remark · ចំណាំ" :value="$cashRequest['remark']" />
    </div>

    <table class="w-full border-collapse text-xs table-fixed">
        <colgroup>
            <col>
            <col class="w-24">
            <col class="w-32">
            <col class="w-32">
        </colgroup>
        <thead>
            <tr>
                <th class="border border-border bg-muted/60 px-2 py-1.5 text-left text-sm font-medium">Description · ពិពណ៌នា</th>
                <th class="border border-border bg-muted/60 px-2 py-1.5 text-left text-sm font-medium">Currency · រូបិយប័ណ្ណ</th>
                <th class="border border-border bg-muted/60 px-2 py-1.5 text-right text-sm font-medium">Amount · ចំនួនទឹកប្រាក់</th>
                <th class="border border-border bg-muted/60 px-2 py-1.5 text-right text-sm font-medium">Amount (USD) · ចំនួនទឹកប្រាក់ (USD)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cashRequest['lines'] as $line)
                <tr>
                    <td class="border border-border p-1 align-top text-sm break-words">{{ $line['description'] }}</td>
                    <td class="border border-border p-1 align-top text-sm">{{ $line['currency'] }}</td>
                    <td class="border border-border p-1 align-top text-sm text-right tabular-nums">{{ PrintFormatter::money($line['amount'], $line['currency'] ?? '') }}</td>
                    <td class="border border-border p-1 align-top text-sm text-right tabular-nums">{{ PrintFormatter::money($line['amountUsd'], 'USD') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="border border-border bg-muted/60 px-2 py-1.5 text-right text-sm font-medium" colspan="3">Total (USD) · សរុប (ដុល្លារ)</td>
                <td class="border border-border bg-muted/60 px-2 py-1.5 text-right text-sm font-medium tabular-nums">{{ PrintFormatter::money($cashRequest['totalUsd'], 'USD') }}</td>
            </tr>
        </tfoot>
    </table>

    <x-print.signatures
        :columns="$columns"
        :labels="['name' => 'Name · ឈ្មោះ', 'position' => 'Position · តួនាទី', 'date' => 'Date · កាលបរិច្ឆេទ']"
    />
</div>
@endsection
