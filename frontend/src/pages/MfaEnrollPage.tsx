import { useEffect, useState, type FormEvent } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from '../auth/authContext'

export function MfaEnrollPage() {
  const { user, pendingStatus, enrollStart, enrollConfirm } = useAuth()
  const [secret, setSecret] = useState('')
  const [uri, setUri] = useState('')
  const [code, setCode] = useState('')
  const [recovery, setRecovery] = useState<string[] | null>(null)
  const [error, setError] = useState('')
  const [copied, setCopied] = useState(false)

  useEffect(() => {
    if (pendingStatus !== 'mfa_enrollment_required') {
      return
    }
    void enrollStart().then((result) => {
      setSecret(result.secret)
      setUri(result.otpauth_uri)
    }).catch(() => setError('تعذر بدء تسجيل التحقق.'))
  }, [enrollStart, pendingStatus])

  if (user && recovery === null) {
    return <Navigate to="/" replace />
  }
  if (pendingStatus !== 'mfa_enrollment_required' && recovery === null) {
    return <Navigate to="/login" replace />
  }

  async function copySecret() {
    if (!secret) {
      return
    }
    try {
      await navigator.clipboard.writeText(secret)
      setCopied(true)
      window.setTimeout(() => setCopied(false), 2000)
    } catch {
      setError('انسخ المفتاح يدوياً من الصندوق أعلاه.')
    }
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setError('')

    if (!/^\d{6}$/.test(code)) {
      setError('اكتب الرقم السداسي الذي يظهر في تطبيق المصادقة فقط. لا تلصق المفتاح الطويل هنا.')
      return
    }

    try {
      const codes = await enrollConfirm(code)
      setRecovery(codes)
    } catch {
      setError('الرقم غير صحيح أو انتهت مدته. انتظر الرقم الجديد في التطبيق ثم أعد المحاولة.')
    }
  }

  if (recovery) {
    return (
      <div className="min-h-screen flex items-center justify-center p-6">
        <div className="w-full max-w-lg bg-white border border-stone-200 rounded-lg p-8 space-y-4">
          <h1 className="text-xl font-semibold">احفظ رموز الاسترداد</h1>
          <p className="text-sm text-stone-600">تظهر مرة واحدة فقط. احفظها في مكان آمن إذا فقدت هاتفك.</p>
          <ul className="font-mono text-sm grid grid-cols-2 gap-2">
            {recovery.map((item) => <li key={item} className="border border-stone-200 px-2 py-1">{item}</li>)}
          </ul>
          <a className="inline-block bg-stone-900 text-white px-4 py-2 rounded" href="/">دخول اللوحة</a>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen flex items-center justify-center p-6">
      <form onSubmit={onSubmit} className="w-full max-w-lg bg-white border border-stone-200 rounded-lg p-8 space-y-4">
        <h1 className="text-xl font-semibold">خطوة أمان واحدة قبل فتح لوحتك</h1>
        <p className="text-sm text-stone-600">
          لأن هذه لوحة تراخيص النظام وليست حساب محل، نربط حسابك بتطبيق على هاتفك
          (مثل Google Authenticator أو Microsoft Authenticator) حتى لا يدخل أحد بكلمة المرور وحدها.
        </p>

        <ol className="text-sm text-stone-700 space-y-2 list-decimal pe-5">
          <li>ثبّت تطبيق مصادقة على هاتفك.</li>
          <li>اختر «إضافة حساب» ثم «إدخال مفتاح يدوياً».</li>
          <li>الصق المفتاح الطويل أدناه داخل التطبيق (ليس هنا).</li>
          <li>ارجع هنا واكتب الـ 6 أرقام التي يظهرها التطبيق.</li>
        </ol>

        {error ? <p className="text-sm text-red-700 bg-red-50 border border-red-100 p-2">{error}</p> : null}

        <div className="bg-stone-50 border border-stone-200 p-3 space-y-2">
          <div className="text-xs text-stone-500">المفتاح — انسخه إلى تطبيق الهاتف</div>
          <div className="font-mono text-base tracking-widest break-all">{secret || '...'}</div>
          <button type="button" className="text-sm underline" onClick={() => void copySecret()}>
            {copied ? 'تم النسخ' : 'نسخ المفتاح'}
          </button>
        </div>

        <details className="text-xs text-stone-500">
          <summary>تفاصيل تقنية (اختياري)</summary>
          <p className="mt-2 break-all">{uri}</p>
        </details>

        <label className="block space-y-1 text-sm">
          <span>الرقم السداسي من التطبيق (مثل 483921)</span>
          <input
            className="w-full border border-stone-300 rounded px-3 py-2 font-mono tracking-widest text-center text-xl"
            inputMode="numeric"
            autoComplete="one-time-code"
            pattern="\d{6}"
            maxLength={6}
            placeholder="------"
            value={code}
            onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
            required
          />
        </label>
        <button className="w-full bg-stone-900 text-white py-2 rounded" type="submit">تأكيد وفتح اللوحة</button>
      </form>
    </div>
  )
}
