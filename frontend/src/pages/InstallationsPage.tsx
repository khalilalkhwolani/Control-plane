import { useEffect, useState, type FormEvent } from 'react'
import { useAuth } from '../auth/authContext'
import api from '../api/client'
import type { Customer, Installation, Paginated } from '../api/types'
import { buildListQuery } from '../lib/buildListQuery'

const emptyForm = {
  customer_id: '',
  installation_uuid: '',
  site_label: '',
  deployment_mode: 'offline',
  activation_state: 'pending',
  notes: '',
}

export function InstallationsPage() {
  const { user } = useAuth()
  const canWrite = user?.permissions.includes('installations.create')
  const canApprove = user?.permissions.includes('installations.update')
  const [search, setSearch] = useState('')
  const [activationState, setActivationState] = useState('')
  const [mode, setMode] = useState('')
  const [page, setPage] = useState(1)
  const [result, setResult] = useState<Paginated<Installation> | null>(null)
  const [customers, setCustomers] = useState<Customer[]>([])
  const [form, setForm] = useState(emptyForm)
  const [error, setError] = useState('')

  async function refreshInstallations() {
    const params = buildListQuery({ search, activation_state: activationState, deployment_mode: mode }, page)
    setResult((await api.get(`/api/v1/installations?${params}`)).data)
  }

  useEffect(() => {
    void api.get('/api/v1/customers?per_page=100').then((response) => setCustomers(response.data.data))
  }, [])

  useEffect(() => {
    const params = buildListQuery({
      search,
      activation_state: activationState,
      deployment_mode: mode,
    }, page)
    void api.get(`/api/v1/installations?${params}`).then((response) => setResult(response.data))
  }, [search, activationState, mode, page])

  async function onCreate(event: FormEvent) {
    event.preventDefault()
    setError('')
    try {
      await api.post('/api/v1/installations', {
        ...form,
        customer_id: Number(form.customer_id),
      })
      setForm(emptyForm)
      setPage(1)
      await refreshInstallations()
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      setError(axiosErr.response?.data?.message ?? 'تعذر حفظ التثبيت.')
    }
  }

  async function onApprove(installation: Installation) {
    setError('')
    try {
      await api.post(`/api/v1/installations/${installation.id}/approve`)
      await refreshInstallations()
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      setError(axiosErr.response?.data?.message ?? 'تعذر اعتماد التثبيت.')
    }
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">التثبيتات والأجهزة</h1>
      <p className="text-sm text-stone-600">الصق رقم التثبيت الذي يولّده الأمر `superpro:installation:show` على خادم العميل.</p>
      <div className="flex gap-3 flex-wrap">
        <input className="border border-stone-300 rounded px-3 py-2" placeholder="بحث بالـ UUID أو الموقع" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1) }} />
        <select className="border border-stone-300 rounded px-3 py-2" value={activationState} onChange={(e) => { setActivationState(e.target.value); setPage(1) }}>
          <option value="">كل حالات التفعيل</option>
          <option value="pending">بانتظار الموافقة</option>
          <option value="approved">معتمد</option>
          <option value="suspended">موقوف</option>
        </select>
        <select className="border border-stone-300 rounded px-3 py-2" value={mode} onChange={(e) => { setMode(e.target.value); setPage(1) }}>
          <option value="">كل أوضاع النشر</option>
          <option value="offline">غير متصل</option>
          <option value="connected">متصل</option>
        </select>
      </div>
      {result && result.meta.total === 0 ? <p className="text-stone-500">لا توجد تثبيتات لهذه التصفية.</p> : null}
      <table className="w-full text-sm bg-white border border-stone-200">
        <thead className="bg-stone-50">
          <tr>
            <th className="text-right p-2">العميل</th>
            <th className="text-right p-2">الموقع</th>
            <th className="text-right p-2">UUID</th>
            <th className="text-right p-2">الوضع</th>
            <th className="text-right p-2">التفعيل</th>
            <th className="text-right p-2">إجراء</th>
          </tr>
        </thead>
        <tbody>
          {result?.data.map((row) => (
            <tr key={row.id} className="border-t border-stone-200">
              <td className="p-2">{row.customer?.display_name}</td>
              <td className="p-2">{row.site_label}</td>
              <td className="p-2 font-mono text-xs">{row.installation_uuid}</td>
              <td className="p-2">{row.deployment_mode}</td>
              <td className="p-2">{row.activation_state}</td>
              <td className="p-2">
                {canApprove && row.activation_state === 'pending' ? <button className="text-sm underline" type="button" onClick={() => void onApprove(row)}>اعتماد</button> : '—'}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      {canWrite ? (
        <form onSubmit={onCreate} className="form-grid bg-white border border-stone-200 p-4 md:grid-cols-2">
          <h2 className="md-span-2 font-semibold">تسجيل تثبيت</h2>
          {error ? <p className="md-span-2 text-red-700 text-sm">{error}</p> : null}
          <select className="border border-stone-300 rounded px-3 py-2" value={form.customer_id} onChange={(e) => setForm({ ...form, customer_id: e.target.value })} required>
            <option value="">اختر العميل</option>
            {customers.map((customer) => (
              <option key={customer.id} value={customer.id}>{customer.display_name}</option>
            ))}
          </select>
          <input className="border border-stone-300 rounded px-3 py-2" placeholder="Installation UUID" value={form.installation_uuid} onChange={(e) => setForm({ ...form, installation_uuid: e.target.value })} required />
          <input className="border border-stone-300 rounded px-3 py-2" placeholder="تسمية الموقع" value={form.site_label} onChange={(e) => setForm({ ...form, site_label: e.target.value })} required />
          <select className="border border-stone-300 rounded px-3 py-2" value={form.deployment_mode} onChange={(e) => setForm({ ...form, deployment_mode: e.target.value })}>
            <option value="offline">غير متصل</option>
            <option value="connected">متصل</option>
          </select>
          <p className="text-sm text-stone-600">يسجل التثبيت بانتظار الموافقة دائماً، ثم يعتمد من زر الاعتماد بعد التحقق.</p>
          <button className="bg-stone-900 text-white px-4 py-2 rounded" type="submit">حفظ</button>
        </form>
      ) : null}
    </div>
  )
}
