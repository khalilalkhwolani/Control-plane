import { useEffect, useState } from 'react'
import api from '../api/client'
import type { AuditEvent, Paginated } from '../api/types'

export function AuditPage() {
  const [action, setAction] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [result, setResult] = useState<Paginated<AuditEvent> | null>(null)
  const [error, setError] = useState('')

  useEffect(() => {
    const params = new URLSearchParams()
    if (action) params.set('action', action)
    if (from) params.set('from', from)
    if (to) params.set('to', to)
    void api.get(`/api/v1/audit-events?${params}`).then((response) => setResult(response.data)).catch(() => setError('ليست لديك صلاحية عرض التدقيق.'))
  }, [action, from, to])

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">سجل التدقيق</h1>
      <p className="text-sm text-stone-600">سجل لاحق فقط. لا يمكن تعديل الأحداث أو حذفها.</p>
      <div className="flex gap-3 flex-wrap">
        <input className="border border-stone-300 rounded px-3 py-2" placeholder="العملية مثل customers.created" value={action} onChange={(e) => setAction(e.target.value)} />
        <input className="border border-stone-300 rounded px-3 py-2" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
        <input className="border border-stone-300 rounded px-3 py-2" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
      </div>
      {error ? <p className="text-red-700 text-sm">{error}</p> : null}
      {result && result.meta.total === 0 ? <p className="text-stone-500">لا توجد أحداث لهذه التصفية.</p> : null}
      <table className="w-full text-sm bg-white border border-stone-200">
        <thead className="bg-stone-50">
          <tr>
            <th className="text-right p-2">العملية</th>
            <th className="text-right p-2">المنفذ</th>
            <th className="text-right p-2">الخطر</th>
            <th className="text-right p-2">معرف الطلب</th>
            <th className="text-right p-2">الوقت</th>
          </tr>
        </thead>
        <tbody>
          {result?.data.map((row) => (
            <tr key={row.id} className="border-t border-stone-200">
              <td className="p-2">{row.action}</td>
              <td className="p-2">{row.actor_email}</td>
              <td className="p-2">{row.risk}</td>
              <td className="p-2 font-mono text-xs">{row.request_id}</td>
              <td className="p-2">{row.created_at}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
