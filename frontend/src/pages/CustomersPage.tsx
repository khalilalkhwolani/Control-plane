import { useEffect, useState, type FormEvent } from 'react'
import { useAuth } from '../auth/authContext'
import api from '../api/client'
import type { Customer, Paginated } from '../api/types'
import { buildListQuery } from '../lib/buildListQuery'

const emptyForm = {
  reference: '',
  legal_name: '',
  display_name: '',
  contract_state: 'prospect',
  notes: '',
  contact_name: '',
  contact_email: '',
  contact_phone: '',
}

export function CustomersPage() {
  const { user } = useAuth()
  const canWrite = user?.permissions.includes('customers.create')
  const [search, setSearch] = useState('')
  const [contractState, setContractState] = useState('')
  const [page, setPage] = useState(1)
  const [result, setResult] = useState<Paginated<Customer> | null>(null)
  const [form, setForm] = useState(emptyForm)
  const [error, setError] = useState('')

  useEffect(() => {
    const params = buildListQuery({ search, contract_state: contractState }, page)
    void api.get(`/api/v1/customers?${params}`).then((response) => setResult(response.data))
  }, [search, contractState, page])

  async function onCreate(event: FormEvent) {
    event.preventDefault()
    setError('')
    try {
      await api.post('/api/v1/customers', {
        reference: form.reference,
        legal_name: form.legal_name,
        display_name: form.display_name,
        contract_state: form.contract_state,
        notes: form.notes || null,
        contacts: form.contact_name
          ? [{ name: form.contact_name, email: form.contact_email || null, phone: form.contact_phone || null, is_primary: true }]
          : [],
      })
      setForm(emptyForm)
      setPage(1)
      const params = new URLSearchParams({ page: '1' })
      if (search) params.set('search', search)
      if (contractState) params.set('contract_state', contractState)
      setResult((await api.get(`/api/v1/customers?${params}`)).data)
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string } } }
      setError(axiosErr.response?.data?.message ?? 'تعذر حفظ العميل.')
    }
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">العملاء</h1>
      <div className="flex gap-3">
        <input
          className="border border-stone-300 rounded px-3 py-2"
          placeholder="بحث بالمرجع أو الاسم"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value)
            setPage(1)
          }}
        />
        <select
          className="border border-stone-300 rounded px-3 py-2"
          value={contractState}
          onChange={(e) => {
            setContractState(e.target.value)
            setPage(1)
          }}
        >
          <option value="">كل الحالات</option>
          <option value="prospect">محتمل</option>
          <option value="active">نشط</option>
          <option value="suspended">موقوف</option>
          <option value="ended">منتهٍ</option>
        </select>
      </div>
      {result && result.meta.total === 0 ? <p className="text-stone-500">لا توجد نتائج لهذه التصفية.</p> : null}
      <table className="w-full text-sm bg-white border border-stone-200">
        <thead className="bg-stone-50">
          <tr>
            <th className="text-right p-2">المرجع</th>
            <th className="text-right p-2">الاسم</th>
            <th className="text-right p-2">العقد</th>
            <th className="text-right p-2">التثبيتات</th>
          </tr>
        </thead>
        <tbody>
          {result?.data.map((row) => (
            <tr key={row.id} className="border-t border-stone-200">
              <td className="p-2 font-mono">{row.reference}</td>
              <td className="p-2">{row.display_name}</td>
              <td className="p-2">{row.contract_state}</td>
              <td className="p-2">{row.installations_count ?? 0}</td>
            </tr>
          ))}
        </tbody>
      </table>
      {result && result.meta.last_page > 1 ? (
        <button className="text-sm underline" type="button" onClick={() => setPage((current) => Math.min(current + 1, result.meta.last_page))}>
          الصفحة التالية
        </button>
      ) : null}
      {canWrite ? (
        <form onSubmit={onCreate} className="form-grid bg-white border border-stone-200 p-4 md:grid-cols-2">
          <h2 className="md-span-2 font-semibold">إضافة عميل</h2>
          {error ? <p className="md-span-2 text-red-700 text-sm">{error}</p> : null}
          <input className="border border-stone-300 rounded px-3 py-2" placeholder="المرجع" value={form.reference} onChange={(e) => setForm({ ...form, reference: e.target.value })} required />
          <input className="border border-stone-300 rounded px-3 py-2" placeholder="الاسم القانوني" value={form.legal_name} onChange={(e) => setForm({ ...form, legal_name: e.target.value })} required />
          <input className="border border-stone-300 rounded px-3 py-2" placeholder="اسم العرض" value={form.display_name} onChange={(e) => setForm({ ...form, display_name: e.target.value })} required />
          <select className="border border-stone-300 rounded px-3 py-2" value={form.contract_state} onChange={(e) => setForm({ ...form, contract_state: e.target.value })}>
            <option value="prospect">محتمل</option>
            <option value="active">نشط</option>
            <option value="suspended">موقوف</option>
            <option value="ended">منتهٍ</option>
          </select>
          <input className="border border-stone-300 rounded px-3 py-2" placeholder="جهة اتصال" value={form.contact_name} onChange={(e) => setForm({ ...form, contact_name: e.target.value })} />
          <input className="border border-stone-300 rounded px-3 py-2" placeholder="بريد جهة الاتصال" value={form.contact_email} onChange={(e) => setForm({ ...form, contact_email: e.target.value })} />
          <textarea className="md-span-2 border border-stone-300 rounded px-3 py-2" placeholder="ملاحظات" value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
          <button className="bg-stone-900 text-white px-4 py-2 rounded" type="submit">حفظ</button>
        </form>
      ) : null}
    </div>
  )
}
