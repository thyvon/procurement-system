@props(['labelKm', 'labelEn', 'value'])

<div class="flex items-start gap-1.5">
    <span class="shrink-0 font-semibold">
        <span class="block">{{ $labelKm }}</span>
        <span class="block">{{ $labelEn }}</span>:
    </span>
    <span class="min-w-0 break-words">{{ \App\Support\PrintFormatter::orDash($value) }}</span>
</div>
