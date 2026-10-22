@props(['columns', 'labels', 'fields' => ['name', 'position', 'date']])

<div
    class="print-signature mt-auto grid gap-8 pt-4"
    style="grid-template-columns: repeat({{ count($columns) }}, minmax(0, 1fr))"
>
    @foreach ($columns as $column)
        <div class="flex flex-col gap-1.5">
            <div class="mb-1 text-center font-semibold">
                <div>{{ $column['title'] }}</div>
                @if(! empty($column['titleKh']))
                    <div>{{ $column['titleKh'] }}</div>
                @endif
            </div>

            @if($column['signature'])
                <div class="flex h-12 items-end justify-center">
                    <img src="{{ $column['signature'] }}" alt="" class="max-h-12 w-auto max-w-full object-contain">
                </div>
            @endif

            @foreach ($fields as $line)
                <div class="flex items-end gap-1.5">
                    <span class="shrink-0">{{ $labels[$line] }}:</span>
                    @if(($column[$line] ?? '') !== '')
                        <span class="min-w-0 flex-1 break-words">{{ $column[$line] }}</span>
                    @else
                        <span aria-hidden class="h-[1.15em] min-w-0 flex-1 border-b border-border"></span>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>
