@props(['src'])

{{-- Single source of truth for the print-header logo footprint (56 × 112 px)
     so every printable document renders it identically; the empty
     placeholder keeps header grids aligned when no logo is set. --}}
@if($src)
    <img src="{{ $src }}" alt="" class="h-14 w-28 shrink-0 object-contain">
@else
    <div aria-hidden class="h-14 w-28 shrink-0"></div>
@endif
