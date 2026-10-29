import { useEffect, useState, type FormEvent } from 'react'
import { useAuth } from '../auth/authContext'
import api from '../api/client'
import type { Paginated, VendorUser } from '../api/types'

export function UsersPage() {
  const { user } = useAuth()
  const canWrite = user?.permissions.includes('users.create')
  const [search, setSearch] = useState('')
  const [result, setResult] = useState<Paginated<VendorUser> | null>(null)
  const [error, setError] = useState('')
  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'license_administrator',
  })

  useEffect(() => {
    const params = new URLSearchParams()
    if (search) params.set('search', search)
    void api.get(`/api/v1/users?${params}`).then((response) => setResult(response.data)).catch(() => setError('ليست لديك صلاحية عرض المستخدمين.'))
  }, [search])

  async function onCreate(event: FormEvent) {
    event.preventDefault()
    setError('')
    try {
      await api.post('/api/v1/users', form)
      setForm({ name: '', email: '', password: '', password_confirmation: '', role: 'license_administrator' })
      setResult((await api.get('/api/v1/users')).data)
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      setError(axiosErr.response?.data?.message ?? 'تعذر إنشاء المستخدم.')
    }
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">مستخدمو اللوحة</h1>
      <p className="text-sm text-stone-600">هؤلاء مشغّلو Control Center (أنت وفريقك). الجدول في قاعدة اللوحة اسمه vendor_users وليس جدول users الخاص بالـ ERP.</p>
      <input className="border border-stone-300 rounded px-3 py-2" placeholder="بحث بالاسم أو البريد" value={search} onChange={(e) => setSearch(e.target.value)} />
      {error ? <p className="text-red-700 text-sm">{error}</p> : null}
      <table className="w-full text-sm bg-white border border-stone-200">
        <thead className="bg-stone-50">
          <tr>
            <th className="text-right p-2">الاسم</th>
            <th className="text-right p-2">البريد</th>
            <th className="text-right p-2">الدور</th>
            <th className="text-right p-2">MFA</th>
            <th className="text-right p-2">الحالة</th>
          </tr>
        </thead>
        <tbody>
          {result?.data.map((row) => (
            <tr key={row.id} className="border-t border-stone-200">
              <td className="p-2">{row.name}</td>
              <td className="p-2">{row.email}</td>
              <td className="p-2">{row.role_label}</td>
              <td className="p-2">{row.mfa_enrolled ? 'مفعّل' : 'غير مسجّل'}</td>
              <td className="p-2">{row.is_active ? 'نشط' : 'معطّل'}</td>
            </tr>
          ))}
        </tbody>
      </table>
      {canWrite ? (
        <form onSubmit={onCreate} className="form-grid bg-white border border-stone-200 p-4 md:grid-cols-2">
          <h2 className="md-span-2 font-semibold">مستخدم جديد — لا يمكن إنشاء مالك من الواجهة</h2>
          <input className="border border-stone-300 rounded px-3 py-2" placeholder="الاسم" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          <input className="border border-stone-300 rounded px-3 py-2" placeholder="البريد" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
          <input className="border border-stone-300 rounded px-3 py-2" placeholder="كلمة المرور (12+)" type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required minLength={12} />
          <input className="border border-stone-300 rounded px-3 py-2" placeholder="تأكيد كلمة المرور" type="password" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} required minLength={12} />
          <select className="border border-stone-300 rounded px-3 py-2" value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })}>
            <option value="license_administrator">مسؤول التراخيص</option>
            <option value="support_engineer">مهندس الدعم</option>
            <option value="release_manager">مدير الإصدارات</option>
            <option value="auditor">مدقق</option>
          </select>
          <button className="bg-stone-900 text-white px-4 py-2 rounded" type="submit">إنشاء</button>
        </form>
      ) : null}
    </div>
  )
}
