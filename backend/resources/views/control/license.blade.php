@extends('layouts.control')
@section('title', 'إصدار ترخيص — Pro One')
@section('content')
<a class="back" href="{{ route('control.index') }}">← رجوع للرئيسية</a>
<section class="hero compact"><div><p class="eyebrow">{{ $installation->customer->reference }}</p><h1>{{ $installation->customer->display_name }}</h1><p>{{ $installation->site_label }} · <code>{{ $installation->installation_uuid }}</code></p></div><span class="badge {{ $installation->activation_state }}">{{ $installation->activation_state === 'approved' ? 'تثبيت معتمد' : 'غير معتمد' }}</span></section>

<div class="grid two license-grid">
    <section class="panel">
        <div class="panel-head"><div><h2>إصدار نسخة ترخيص جديدة</h2><p>كل إصدار يأخذ رقماً أعلى، والـERP يرفض الرجوع لترخيص أقدم.</p></div></div>
        @unless($signingReady)<div class="notice warning">اضبط مفتاح التوقيع أولاً في إعدادات الخادم. المفتاح الخاص لا يُحفظ داخل المشروع.</div>@endunless
        <form method="post" action="{{ route('control.licenses.store', $installation) }}" class="stack">@csrf
            <label>الباقة<input name="edition" value="{{ old('edition', 'pro') }}" pattern="[a-z][a-z0-9._-]*" required></label>
            <fieldset><legend>الميزات المسموحة</legend>@foreach($features as $feature)<label class="check"><input type="checkbox" name="features[]" value="{{ $feature->key }}" @checked(in_array($feature->key, old('features', []), true))><span><strong>{{ $feature->name_ar }}</strong><small>{{ $feature->description_ar }} · <code>{{ $feature->key }}</code></small></span></label>@endforeach</fieldset>
            <label>تاريخ الانتهاء <small>(اتركه فارغاً لترخيص دائم)</small><input type="date" name="expires_on" value="{{ old('expires_on') }}"></label>
            <label>سبب الإصدار<textarea name="reason" rows="3" required placeholder="ترخيص أولي حسب العقد رقم ...">{{ old('reason') }}</textarea></label>
            <button class="primary" type="submit" @disabled(!$signingReady || $installation->activation_state !== 'approved')>إصدار ملف موقّع</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><div><h2>سجل الإصدارات</h2><p>السجل لا يُعدّل ولا يُحذف.</p></div></div>
        <div class="timeline">
        @forelse($installation->licenseRevisions as $revision)
            <article><span>{{ $revision->sequence }}</span><div><strong>{{ $revision->edition }} · {{ count($revision->features) }} ميزات</strong><small>{{ $revision->issued_at->format('Y-m-d H:i') }} · {{ $revision->issuer?->name ?? 'مستخدم محذوف' }}</small><small>{{ $revision->expires_at ? 'ينتهي '.$revision->expires_at->format('Y-m-d') : 'دائم' }}</small><p>{{ $revision->reason }}</p><a class="secondary" href="{{ route('control.licenses.download', $revision) }}">تنزيل ملف الترخيص</a></div></article>
        @empty<div class="empty">لم يصدر أي ترخيص لهذا التثبيت.</div>@endforelse
        </div>
    </section>
</div>
@endsection
