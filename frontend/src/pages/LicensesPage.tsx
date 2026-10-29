import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '../api/client'
import type { FeatureDefinition, Installation, LicenseRevision, Paginated } from '../api/types'
import { useAuth } from '../auth/authContext'

type LicenseForm = {
  installationId: string
  edition: string
  features: string[]
  expiresOn: string
  reason: string
}

const initialForm: LicenseForm = {
  installationId: '',
  edition: 'pro',
  features: [],
  expiresOn: '',
  reason: '',
}

export function LicensesPage() {
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [form, setForm] = useState<LicenseForm>(initialForm)
  const [error, setError] = useState('')
  const canIssue = user?.permissions.includes('licenses.issue') ?? false

  const licenses = useQuery({
    queryKey: ['licenses'],
    queryFn: async () => (await api.get<Paginated<LicenseRevision>>('/api/v1/licenses')).data,
  })
  const installations = useQuery({
    queryKey: ['installations', 'license-form'],
    queryFn: async () => (await api.get<Paginated<Installation>>('/api/v1/installations?per_page=100')).data.data,
  })
  const features = useQuery({
    queryKey: ['features'],
    queryFn: async () => (await api.get<{ data: FeatureDefinition[] }>('/api/v1/features')).data.data,
  })

  const approvedInstallations = useMemo(
    () => (installations.data ?? []).filter((installation) => installation.activation_state === 'approved'),
    [installations.data],
  )

  useEffect(() => {
    if (!form.installationId && approvedInstallations.length === 1) {
      setForm((current) => ({ ...current, installationId: String(approvedInstallations[0].id) }))
    }
  }, [approvedInstallations, form.installationId])

  const issue = useMutation({
    mutationFn: async () => api.post(`/api/v1/installations/${form.installationId}/licenses`, {
      edition: form.edition,
      features: form.features,
      expires_on: form.expiresOn || null,
      reason: form.reason,
    }),
    onSuccess: async () => {
      setForm(initialForm)
      await queryClient.invalidateQueries({ queryKey: ['licenses'] })
    },
  })

  function toggleFeature(key: string) {
    setForm((current) => ({
      ...current,
      features: current.features.includes(key)
        ? current.features.filter((item) => item !== key)
        : [...current.features, key],
    }))
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setError('')
    try {
      await issue.mutateAsync()
    } catch (err: unknown) {
      const response = err as { response?: { data?: { message?: string } } }
      setError(response.response?.data?.message ?? 'تعذر إصدار ملف الترخيص.')
    }
  }

  async function download(license: LicenseRevision) {
    setError('')
    try {
      const response = await api.get(`/api/v1/licenses/${license.id}/download`, { responseType: 'blob' })
      const href = URL.createObjectURL(response.data as Blob)
      const anchor = document.createElement('a')
      anchor.href = href
      anchor.download = `pro-one-license-${license.license_uuid}.json`
      anchor.click()
      URL.revokeObjectURL(href)
    } catch {
      setError('تعذر تنزيل ملف الترخيص.')
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">التراخيص</h1>
        <p className="text-sm text-stone-600">الإصدار ينشئ ملفاً موقّعاً. نزّله ثم استورده في ERP المطابق لمعرّف التثبيت.</p>
      </div>
      {error ? <p className="text-red-700 text-sm">{error}</p> : null}
      {canIssue ? (
        <form onSubmit={onSubmit} className="form-grid bg-white border border-stone-200 p-4 rounded md:grid-cols-2">
          <h2 className="md-span-2 font-semibold">إصدار ملف ترخيص</h2>
          <label className="block space-y-1 text-sm">
            <span>التثبيت المعتمد</span>
            <select className="w-full border border-stone-300 rounded px-3 py-2" value={form.installationId} onChange={(event) => setForm({ ...form, installationId: event.target.value })} required>
              <option value="">اختر التثبيت</option>
              {approvedInstallations.map((installation) => (
                <option key={installation.id} value={installation.id}>
                  {installation.customer?.display_name} — {installation.site_label}
                </option>
              ))}
            </select>
          </label>
          <label className="block space-y-1 text-sm">
            <span>الإصدار التجاري</span>
            <input className="w-full border border-stone-300 rounded px-3 py-2" value={form.edition} onChange={(event) => setForm({ ...form, edition: event.target.value })} required pattern="[a-z][a-z0-9._-]*" />
          </label>
          <label className="block space-y-1 text-sm">
            <span>تاريخ الانتهاء (اختياري)</span>
            <input className="w-full border border-stone-300 rounded px-3 py-2" type="date" value={form.expiresOn} onChange={(event) => setForm({ ...form, expiresOn: event.target.value })} />
          </label>
          <label className="block space-y-1 text-sm">
            <span>سبب الإصدار</span>
            <input className="w-full border border-stone-300 rounded px-3 py-2" value={form.reason} onChange={(event) => setForm({ ...form, reason: event.target.value })} minLength={5} required />
          </label>
          <fieldset className="md-span-2 border border-stone-200 rounded p-3">
            <legend className="px-1 text-sm">الميزات الممنوحة</legend>
            {features.isPending ? <p className="text-sm text-stone-500">جاري تحميل الميزات...</p> : null}
            <div className="grid gap-2 md:grid-cols-2">
              {features.data?.filter((feature) => feature.is_active).map((feature) => (
                <label className="flex gap-2 items-start text-sm" key={feature.id}>
                  <input type="checkbox" checked={form.features.includes(feature.key)} onChange={() => toggleFeature(feature.key)} />
                  <span><strong>{feature.name_ar}</strong><br /><code className="text-stone-500">{feature.key}</code></span>
                </label>
              ))}
            </div>
          </fieldset>
          <button disabled={issue.isPending || approvedInstallations.length === 0} className="bg-stone-900 text-white px-4 py-2 rounded disabled:opacity-60" type="submit">
            {issue.isPending ? 'جاري التوقيع...' : 'إصدار الترخيص'}
          </button>
          {approvedInstallations.length === 0 ? <p className="text-sm text-stone-500">لا يوجد تثبيت معتمد يمكن إصدار ترخيص له.</p> : null}
        </form>
      ) : null}
      <section className="space-y-3">
        <h2 className="text-lg font-semibold">سجل الإصدارات</h2>
        {licenses.isPending ? <p>جاري تحميل التراخيص...</p> : null}
        {licenses.isError ? <p className="text-red-700">تعذر تحميل التراخيص.</p> : null}
        {licenses.data?.data.length === 0 ? <p className="text-stone-500">لم يصدر أي ترخيص بعد.</p> : null}
        {licenses.data?.data.length ? (
          <table className="w-full text-sm bg-white border border-stone-200">
            <thead className="bg-stone-50"><tr><th className="text-right p-2">العميل/الموقع</th><th className="text-right p-2">التسلسل</th><th className="text-right p-2">الإصدار</th><th className="text-right p-2">الميزات</th><th className="text-right p-2">الانتهاء</th><th className="text-right p-2">الملف</th></tr></thead>
            <tbody>{licenses.data.data.map((license) => (
              <tr className="border-t border-stone-200" key={license.id}>
                <td className="p-2">{license.installation?.customer?.display_name ?? '—'}<br /><span className="text-xs text-stone-500">{license.installation?.site_label}</span></td>
                <td className="p-2">#{license.sequence}</td>
                <td className="p-2">{license.edition}</td>
                <td className="p-2">{license.features.join('، ') || 'بدون ميزات'}</td>
                <td className="p-2">{license.expires_at ? new Date(license.expires_at).toLocaleDateString('ar-SA') : 'دائم'}</td>
                <td className="p-2"><button className="text-sm underline" type="button" onClick={() => void download(license)}>تنزيل</button></td>
              </tr>
            ))}</tbody>
          </table>
        ) : null}
      </section>
    </div>
  )
}
