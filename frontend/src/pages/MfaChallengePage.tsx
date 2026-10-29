import { useState, type FormEvent } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from '../auth/authContext'

export function MfaChallengePage() {
  const { user, pendingStatus, challenge } = useAuth()
  const [code, setCode] = useState('')
  const [recoveryCode, setRecoveryCode] = useState('')
  const [error, setError] = useState('')

  if (user) {
    return <Navigate to="/" replace />
  }
  if (pendingStatus !== 'mfa_required') {
    return <Navigate to="/login" replace />
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setError('')
    try {
      await challenge(code || undefined, recoveryCode || undefined)
    } catch {
      setError('رمز التحقق غير صحيح.')
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center p-6">
      <form onSubmit={onSubmit} className="w-full max-w-md bg-white border border-stone-200 rounded-lg p-8 space-y-4">
        <h1 className="text-xl font-semibold">التحقق متعدد العوامل</h1>
        {error ? <p className="text-sm text-red-700">{error}</p> : null}
        <label className="block space-y-1 text-sm">
          <span>رمز التطبيق</span>
          <input className="w-full border border-stone-300 rounded px-3 py-2" value={code} onChange={(e) => setCode(e.target.value)} />
        </label>
        <label className="block space-y-1 text-sm">
          <span>أو رمز الاسترداد</span>
          <input className="w-full border border-stone-300 rounded px-3 py-2" value={recoveryCode} onChange={(e) => setRecoveryCode(e.target.value)} />
        </label>
        <button className="w-full bg-stone-900 text-white py-2 rounded" type="submit">تأكيد</button>
      </form>
    </div>
  )
}
