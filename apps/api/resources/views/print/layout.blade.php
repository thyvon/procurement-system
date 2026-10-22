<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>{!! $css !!}</style>
    @yield('page-css')
</head>
<body>
    <div class="print-area">
        @yield('content')
    </div>
</body>
</html>
