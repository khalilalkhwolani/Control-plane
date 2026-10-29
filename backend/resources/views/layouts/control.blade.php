<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Pro One Control Center')</title>
    <link rel="stylesheet" href="{{ asset('css/control-center.css') }}">
</head>
<body>
@auth
    <header class="topbar">
        <a class="brand" href="{{ route('control.index') }}"><span class="brand-mark">P1</span><span>Pro One Control Center<small>إدارة عملاء وتراخيص النظام</small></span></a>
        <div class="account"><span>{{ auth()->user()->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button class="link-button" type="submit">خروج</button></form></div>
    </header>
@endauth
<main class="page @guest login-page @endguest">
    @if(session('success'))<div class="notice success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="notice error"><strong>لم تكتمل العملية:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
</body>
</html>
