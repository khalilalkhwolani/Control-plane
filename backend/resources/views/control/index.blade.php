@extends('layouts.control')
@section('title', 'الرئيسية — Pro One')
@section('content')
<section class="hero">
    <div><p class="eyebrow">المسار الأساسي</p><h1>إدارة ترخيص العميل من مكان واحد</h1><p>أضف العميل، اربط تثبيت جهازه، اعتمده، ثم أصدر ملف الترخيص الموقّع.</p></div>
    <div class="status {{ $signingReady ? 'ready' : 'warning' }}"><span></span>{{ $signingReady ? 'التوقيع جاهز' : 'مفتاح التوقيع غير مضبوط' }}</div>
</section>

<section class="stats">
    <article><strong>{{ $customers->count() }}</strong><span>العملاء</span></article>
    <article><strong>{{ $installations->count() }}</strong><span>التثبيتات</span></article>
    <article><strong>{{ $licenseCount }}</strong><span>التراخيص الصادرة</span></article>
</section>

<section class="steps">
    <article><span>1</span><div><strong>أضف العميل</strong><small>الشركة التي اشترت Pro One</small></div></article>
    <article><span>2</span><div><strong>سجّل التثبيت</strong><small>انسخ المعرّف من نظام العميل</small></div></article>
    <article><span>3</span><div><strong>أصدر الترخيص</strong><small>اختر الميزات ونزّل الملف</small></div></article>
</section>

<div class="grid two">
    <section class="panel">
        <div class="panel-head"><div><h2>إضافة عميل</h2><p>العميل هنا هو مشتري نظام Pro One.</p></div></div>
        <form method="post" action="{{ route('control.customers.store') }}" class="form-grid">@csrf
            <label>مرجع العميل<input name="reference" value="{{ old('reference') }}" placeholder="CUST-001" required></label>
            <label>الاسم المختصر<input name="display_name" value="{{ old('display_name') }}" placeholder="متجر الأمانة" required></label>
            <label class="wide">الاسم القانوني<input name="legal_name" value="{{ old('legal_name') }}" placeholder="مؤسسة الأمانة التجارية" required></label>
            <label class="wide">ملاحظات<textarea name="notes" rows="2">{{ old('notes') }}</textarea></label>
            <button class="primary wide" type="submit">حفظ العميل</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><div><h2>تسجيل تثبيت</h2><p>يظل معلّقاً حتى تطابق المعرّف وتعتمده.</p></div></div>
        <form method="post" action="{{ route('control.installations.store') }}" class="form-grid">@csrf
            <label class="wide">العميل<select name="customer_id" required><option value="">اختر العميل</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->display_name }} — {{ $customer->reference }}</option>@endforeach</select></label>
            <label class="wide">معرّف تثبيت Pro One
                <input class="ltr" name="installation_uuid" value="{{ old('installation_uuid') }}" placeholder="xxxxxxxx-xxxx-4xxx-xxxx-xxxxxxxxxxxx" minlength="36" maxlength="36" pattern="[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}" required>
                <small style="color:#637381;font-weight:400">انسخه من شاشة ترخيص النظام داخل ERP عند العميل. لا تكتب 1 أو رقم العميل؛ هذا المعرّف يربط الترخيص بنسخة واحدة فقط.</small>
            </label>
            <label>اسم الموقع<input name="site_label" value="{{ old('site_label') }}" placeholder="الفرع الرئيسي" required></label>
            <label>طريقة التشغيل<select name="deployment_mode"><option value="offline">محلي بدون إنترنت</option><option value="connected">متصل</option></select></label>
            <button class="primary wide" type="submit" @disabled($customers->isEmpty())>تسجيل التثبيت</button>
        </form>
    </section>
</div>

<section class="panel table-panel">
    <div class="panel-head"><div><h2>التثبيتات والتراخيص</h2><p>لا تصدر ترخيصاً قبل مطابقة المعرّف الظاهر داخل نظام العميل.</p></div></div>
    @if($installations->isEmpty())
        <div class="empty">لا توجد تثبيتات بعد. ابدأ بإضافة العميل ثم تثبيته.</div>
    @else
        <div class="table-wrap"><table><thead><tr><th>العميل</th><th>الموقع</th><th>معرّف التثبيت</th><th>الحالة</th><th>آخر ترخيص</th><th></th></tr></thead><tbody>
        @foreach($installations as $installation)
            @php($latest = $installation->licenseRevisions->first())
            <tr>
                <td><strong>{{ $installation->customer->display_name }}</strong><small>{{ $installation->customer->reference }}</small></td>
                <td>{{ $installation->site_label }}<small>{{ $installation->deployment_mode === 'offline' ? 'محلي' : 'متصل' }}</small></td>
                <td><code>{{ $installation->installation_uuid }}</code></td>
                <td><span class="badge {{ $installation->activation_state }}">{{ ['pending'=>'معلّق','approved'=>'معتمد','suspended'=>'موقوف'][$installation->activation_state] }}</span></td>
                <td>@if($latest)نسخة {{ $latest->sequence }}<small>{{ $latest->issued_at->format('Y-m-d') }}</small>@else<span class="muted">لم يصدر</span>@endif</td>
                <td class="actions">
                    @if($installation->activation_state === 'pending')<form method="post" action="{{ route('control.installations.approve', $installation) }}">@csrf<button class="secondary" type="submit">اعتماد</button></form>@endif
                    @if($installation->activation_state === 'approved')<a class="primary small" href="{{ route('control.licenses.create', $installation) }}">إدارة الترخيص</a>@endif
                </td>
            </tr>
        @endforeach
        </tbody></table></div>
    @endif
</section>
@endsection
