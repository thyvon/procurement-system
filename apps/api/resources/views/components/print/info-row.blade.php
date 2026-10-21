@props(['label', 'value'])

<div class="flex gap-1.5">
    <span class="shrink-0 text-muted-foreground">{{ $label }}:</span>
    <span class="min-w-0 flex-1 break-words">{{ \App\Support\PrintFormatter::orDash($value) }}</span>
</div>
