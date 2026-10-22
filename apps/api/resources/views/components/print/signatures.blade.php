@props(['columns'])

{{-- Official sheets keep the signature block compact: the bilingual step
     title, the signer's signature, the name and position values without
     labels, and a single English "Date" line. Rows cap at four columns;
     a longer list wraps and a short last row is centered. --}}
@php
    $perRow = max(count($columns) > 4 ? 4 : count($columns), 1);
    $rows = array_chunk($columns, $perRow);
    $basis = 'calc((100% - '.($perRow - 1).' * 2rem) / '.$perRow.')';
@endphp

<div class="print-signature flex flex-col gap-8 pt-4 text-xs">
    @foreach ($rows as $row)
        <div class="flex justify-center gap-8">
            @foreach ($row as $column)
                <div class="flex min-w-0 flex-col gap-1.5" style="flex: 0 1 {{ $basis }}">
                    <div class="mb-1 text-center font-semibold">
                        @if(! empty($column['titleKh']))
                            <div>{{ $column['titleKh'] }}</div>
                        @endif
                        <div>{{ $column['title'] }}</div>
                    </div>

                    @if($column['signature'])
                        <div class="flex h-12 items-end justify-center">
                            <img src="{{ $column['signature'] }}" alt="" class="max-h-12 w-auto max-w-full object-contain">
                        </div>
                    @endif

                    @if(($column['name'] ?? '') !== '')
                        <div class="min-w-0 break-words font-bold">{{ $column['name'] }}</div>
                    @endif

                    @if(($column['position'] ?? '') !== '')
                        <div class="min-w-0 break-words font-bold">{{ $column['position'] }}</div>
                    @endif

                    <div class="flex items-end gap-1.5">
                        <span class="shrink-0">Date:</span>
                        @if(($column['date'] ?? '') !== '')
                            <span class="min-w-0 flex-1 break-words">{{ $column['date'] }}</span>
                        @else
                            <span aria-hidden class="h-[1.15em] min-w-0 flex-1 border-b border-border"></span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
