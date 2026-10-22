@use(App\Support\PrintFormatter)

@extends('print.layout')

@section('title', 'Price Evaluation Form · '.$evaluation['code'])

@section('content')
@php
    $head = 'border border-border bg-muted/60 px-2 py-1.5 text-center text-sm font-medium align-middle';
    $cell = 'border border-border p-1 align-middle text-sm';
    $criteriaCell = 'border-y border-border bg-muted/40 px-2 py-1 text-left text-sm font-bold align-middle';
    $printCols = count($quotations) <= 2
        ? ['brand' => 'print:w-[140px]', 'unit' => 'print:w-[70px]', 'total' => 'print:w-[80px]']
        : ['brand' => 'print:w-[95px]', 'unit' => 'print:w-[70px]', 'total' => 'print:w-[75px]'];
    $tableWidth = 230 + 400 + count($quotations) * 315;
    $currency = $evaluation['currency'];
    $totalLabels = [
        'subTotal' => 'SubTotal:',
        'discount' => 'Discount',
        'vat' => 'VAT 10%',
        'grandTotal' => 'Grand Total',
    ];
    $criteriaLabels = [
        'price' => 'Price',
        'quality' => 'Quality',
        'leadTime' => 'Lead time on service/production/goods',
        'warranty' => 'Warranty, (Services/Spare Parts/Goods)',
        'paymentTerms' => 'Term Payment/Deposit',
        'otherRemarks' => 'Other Remarks',
    ];
@endphp

<div class="flex min-h-[186mm] flex-col gap-3 text-sm text-foreground">
    <div class="grid grid-cols-[1fr_auto_1fr] items-start gap-4">
        @if($logo)
            <img src="{{ $logo }}" alt="" class="h-14 w-28 shrink-0 object-contain">
        @else
            <div aria-hidden class="h-14 w-28 shrink-0"></div>
        @endif
        <h1 class="place-self-center text-center text-lg font-bold tracking-tight">Price Evaluation Form</h1>
        <div class="justify-self-end text-left leading-relaxed">
            <div>Code: MJQE0055</div>
            <div>Version 4.1</div>
        </div>
    </div>

    <div class="min-w-0 space-y-3">
        <div class="w-full max-w-full overflow-x-auto border border-border">
            <table class="w-full border-collapse text-sm table-fixed" style="width: 100%; min-width: {{ $tableWidth }}px">
                <colgroup>
                    <col class="w-8">
                    <col class="w-[90px] print:w-[80px]">
                    <col class="w-[400px] print:w-auto">
                    <col class="w-12 print:w-10">
                    <col class="w-15 print:w-12">
                    @foreach($quotations as $panel)
                        <col class="w-[120px] {{ $printCols['brand'] }}">
                        <col class="w-[90px] {{ $printCols['unit'] }}">
                        <col class="w-[105px] {{ $printCols['total'] }}">
                    @endforeach
                </colgroup>
                <thead>
                    <tr>
                        <th class="{{ $head }} w-8" rowspan="3">No.</th>
                        <th class="{{ $head }} w-[90px]" rowspan="3">Item Code</th>
                        <th class="{{ $head }}" rowspan="3">Item Description</th>
                        <th class="{{ $head }} w-12" rowspan="3">QTY</th>
                        <th class="{{ $head }} w-15" rowspan="3">UoM</th>
                        @foreach($quotations as $panel)
                            <th class="{{ $head }} min-w-[15px]" colspan="3">Quotation {{ $panel['label'] }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach($quotations as $panel)
                            <th class="{{ $cell }}" colspan="3">
                                <div class="space-y-0.5 text-left text-sm">
                                    <p class="flex whitespace-pre-wrap">
                                        <span class="min-w-9 shrink-0 font-semibold">Name</span>
                                        <span class="min-w-0 flex-1 break-words font-normal">: {{ PrintFormatter::orDash($panel['supplierName']) }}</span>
                                    </p>
                                    <p class="flex whitespace-pre-wrap">
                                        <span class="min-w-9 shrink-0 font-semibold">Add</span>
                                        <span class="min-w-0 flex-1 break-words font-normal">: {{ PrintFormatter::orDash($panel['address']) }}</span>
                                    </p>
                                    <p class="flex whitespace-pre-wrap">
                                        <span class="min-w-9 shrink-0 font-semibold">Tel</span>
                                        <span class="min-w-0 flex-1 break-words font-normal">: {{ PrintFormatter::orDash($panel['phone']) }}</span>
                                    </p>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach($quotations as $panel)
                            <th class="{{ $head }} w-[120px]">Brand</th>
                            <th class="{{ $head }} w-[90px]">Unit cost</th>
                            <th class="{{ $head }} w-[105px]">Total Cost</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $itemIndex => $item)
                        <tr class="border-b border-border hover:bg-muted/30">
                            <td class="{{ $cell }} text-center text-sm text-muted-foreground">
                                <div class="flex h-7 items-center justify-center">{{ $itemIndex + 1 }}</div>
                            </td>
                            <td class="{{ $cell }}">
                                <div class="flex h-7 items-center justify-center">{{ PrintFormatter::orDash($item['itemCode']) }}</div>
                            </td>
                            <td class="{{ $cell }}">
                                <div class="flex min-h-[56px] items-center whitespace-pre-wrap break-words">{{ PrintFormatter::orDash($item['description']) }}</div>
                            </td>
                            <td class="{{ $cell }}">
                                <div class="flex h-7 items-center justify-center tabular-nums">{{ PrintFormatter::numeric($item['qty']) }}</div>
                            </td>
                            <td class="{{ $cell }}">
                                <div class="flex h-7 items-center justify-center">{{ PrintFormatter::orDash($item['uom']) }}</div>
                            </td>
                            @foreach($quotations as $panel)
                                @php($pricing = $panel['pricing'][$item['uid']] ?? [])
                                <td class="{{ $cell }}{{ ($pricing['selected'] ?? false) ? ' bg-green-600/10' : '' }}">
                                    <div class="flex h-7 items-center">{{ PrintFormatter::orDash($pricing['brand'] ?? null) }}</div>
                                </td>
                                <td class="{{ $cell }}{{ ($pricing['selected'] ?? false) ? ' bg-green-600/10' : '' }}">
                                    <div class="flex h-7 items-center justify-end tabular-nums">
                                        {{ ($pricing['unitCost'] ?? null) !== null ? PrintFormatter::money($pricing['unitCost'], $currency) : '—' }}
                                    </div>
                                </td>
                                <td class="{{ $cell }}{{ ($pricing['selected'] ?? false) ? ' bg-green-600/10' : '' }}">
                                    <div class="flex h-7 items-center justify-end tabular-nums">
                                        {{ ($pricing['lineTotal'] ?? null) !== null ? PrintFormatter::money($pricing['lineTotal'], $currency) : '—' }}
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach

                    @foreach($totalLabels as $totalKey => $totalLabel)
                        <tr @class(['border-t-2 border-border' => $totalKey === 'grandTotal'])>
                            <td class="{{ $cell }} bg-muted/20" colspan="5">
                                <div class="flex items-center justify-end gap-2 pr-2 text-sm font-bold">
                                    <span>{{ $totalLabel }}</span>
                                </div>
                            </td>
                            @foreach($quotations as $panel)
                                <td class="{{ $cell }}" colspan="3">
                                    <div class="flex h-7 items-center justify-end text-sm tabular-nums{{ $totalKey === 'grandTotal' ? ' font-bold' : '' }}">
                                        @if($totalKey === 'subTotal')
                                            {{ PrintFormatter::money($panel['subTotal'], $currency) }}
                                        @elseif($totalKey === 'grandTotal')
                                            {{ PrintFormatter::money($panel['grandTotal'], $currency) }}
                                        @elseif($totalKey === 'discount')
                                            {{ $panel['discountDisplay'] !== '' ? PrintFormatter::money($panel['discountAmount'], $currency) : '—' }}
                                        @else
                                            {{ $panel['vatDisplay'] !== '' ? PrintFormatter::money($panel['vatAmount'], $currency) : '—' }}
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach

                    @php($criteriaNo = 0)
                    @foreach($criteriaLabels as $criteriaKey => $criteriaLabel)
                        @php($criteriaNo++)
                        <tr>
                            <td class="{{ $criteriaCell }} border-l w-10"></td>
                            <td class="{{ $criteriaCell }} min-w-[120px]"></td>
                            <td class="{{ $criteriaCell }}" colspan="3">
                                <div class="text-left">
                                    <span>{{ $criteriaNo }}. {{ $criteriaLabel }}</span>
                                </div>
                            </td>
                            @foreach($quotations as $panel)
                                <td class="{{ $cell }}" colspan="3">
                                    <div class="flex h-full min-h-7 items-center justify-center whitespace-pre-wrap break-words text-center">
                                        @if($panel['criteria'][$criteriaKey] !== '')
                                            {{ $panel['criteria'][$criteriaKey] }}
                                        @else
                                            <span class="text-muted-foreground">—</span>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="border border-border px-2 py-1.5">
        <span class="font-semibold">Basis of supplier recommend:</span>
        {{ PrintFormatter::orDash($evaluation['recommendationBasis']) }}
    </div>

    <x-print.signatures :columns="$columns" />
</div>
@endsection
