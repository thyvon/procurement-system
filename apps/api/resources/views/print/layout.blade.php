<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>{!! $css !!}</style>
    @yield('page-css')
</head>
<body>
    @isset($watermark)
        {{-- Not-yet-approved documents are not official compliance copies:
             a big diagonal stamp, repeated by the renderer on every page. --}}
        <div class="print-watermark" aria-hidden="true">
            <div class="print-watermark__status">{{ $watermark['kh'] }}</div>
            <div class="print-watermark__status">{{ $watermark['en'] }}</div>
            <div class="print-watermark__flag">ឯកសារមិនផ្លូវការ · NOT OFFICIAL</div>
        </div>
    @endisset
    <div class="print-area">
        @yield('content')
    </div>
</body>
</html>
