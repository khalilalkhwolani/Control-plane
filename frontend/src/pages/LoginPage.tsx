import { useState, type FormEvent } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from '../auth/authContext'

const developmentEmail = import.meta.env.DEV ? (import.meta.env.VITE_DEV_LOGIN_EMAIL ?? '') : ''
const developmentPassword = import.meta.env.DEV ? (import.meta.env.VITE_DEV_LOGIN_PASSWORD ?? '') : ''

export function LoginPage() {
  const { user, pendingStatus, login } = useAuth()
  const [email, setEmail] = useState(developmentEmail)
  const [password, setPassword] = useState(developmentPassword)
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  if (user) {
    return <Navigate to="/" replace />
  }
  if (pendingStatus === 'mfa_enrollment_required') {
    return <Navigate to="/mfa/enroll" replace />
  }
  if (pendingStatus === 'mfa_required') {
    return <Navigate to="/mfa/challenge" replace />
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setSubmitting(true)
    setError('')
    try {
      await login(email, password)
    } catch (err: unknown) {
      const axiosErr = err as { response?: { status?: number; data?: { message?: string; errors?: { email?: string[] } } } }
      if (axiosErr.response?.status === 419 || axiosErr.response?.data?.message?.toLowerCase().includes('csrf')) {
        setError('انتهت جلسة الحماية. حدّث الصفحة ثم أعد المحاولة.')
      } else {
        setError(
          axiosErr.response?.data?.errors?.email?.[0]
            ?? axiosErr.response?.data?.message
            ?? 'تعذر تسجيل الدخول.',
        )
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="login-page" dir="rtl">
      <aside className="login-intro"><img className="login-logo" src="/logo.png" alt="Pro One" /><p className="eyebrow">PRO ONE CONTROL CENTER</p><h2>منتجك يتوسع.<br />وإدارته تبدأ من هنا.</h2><p>مساحة واحدة لمتابعة العملاء، اعتماد التثبيتات وإصدار التراخيص بالميزات المناسبة لكل عميل.</p><div className="login-points"><span>01 · سجّل عملاءك</span><span>02 · اعتمد تثبيتاتهم</span><span>03 · أصدر تراخيصهم</span></div></aside>
      <form onSubmit={onSubmit} className="w-full max-w-md bg-white border border-stone-200 rounded-lg p-8 space-y-4">
        <p className="text-sm text-stone-500">لوحة تراخيص Pro One</p>
        <h1 className="text-2xl font-semibold">مرحبًا بعودتك</h1>
        <p className="text-sm text-stone-600">سجّل الدخول إلى مساحة إدارة المنتج بحسابك المعتمد.</p>
        {import.meta.env.DEV && developmentEmail && developmentPassword ? <p className="dev-login-note">بيئة تطوير محلية · بيانات الدخول معبأة تلقائيًا</p> : null}
        {error ? <p className="text-sm text-red-700 bg-red-50 border border-red-100 p-2">{error}</p> : null}
        <label className="block space-y-1 text-sm">
          <span>البريد</span>
          <input className="w-full border border-stone-300 rounded px-3 py-2" type="email" autoComplete="username" dir="ltr" value={email} onChange={(e) => setEmail(e.target.value)} required />
        </label>
        <label className="block space-y-1 text-sm">
          <span>كلمة المرور</span>
          <input className="w-full border border-stone-300 rounded px-3 py-2" type="password" autoComplete="current-password" dir="ltr" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={12} />
        </label>
        <button disabled={submitting} className="w-full bg-stone-900 text-white py-2 rounded disabled:opacity-60" type="submit">
          {submitting ? 'جاري التحقق...' : 'متابعة'}
        </button>
      </form>
    </div>
  )
}
