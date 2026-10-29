@extends('layouts.control')
@section('title', 'دخول لوحة Pro One')
@section('content')
<section class="login-card">
    <div class="brand large"><span class="brand-mark">P1</span><span>Pro One Control Center<small>لوحتك الخاصة لإدارة تراخيص العملاء</small></span></div>
    <h1>تسجيل الدخول</h1>
    <p class="muted">هذه اللوحة تخص مالك Pro One وفريقه فقط، ولا تُثبّت عند العميل.</p>
    <form method="post" action="{{ route('login.store') }}" class="stack">@csrf
        <label>البريد الإلكتروني<input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
        <label>كلمة المرور<input name="password" type="password" required autocomplete="current-password"></label>
        <button class="primary" type="submit">دخول</button>
    </form>
</section>
@endsection
