<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>{!! $css !!}</style>
</head>
<body>
    <div class="print-area">
        @yield('content')
    </div>
</body>
</html>
