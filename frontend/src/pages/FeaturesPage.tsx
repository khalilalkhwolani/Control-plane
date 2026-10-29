import { useQuery } from '@tanstack/react-query'
import api from '../api/client'
import type { FeatureDefinition } from '../api/types'

export function FeaturesPage() {
  const features = useQuery({
    queryKey: ['features'],
    queryFn: async () => (await api.get<{ data: FeatureDefinition[] }>('/api/v1/features')).data.data,
  })

  if (features.isPending) {
    return <p>جاري تحميل كتالوج الميزات...</p>
  }
  if (features.isError) {
    return <p className="text-red-700">تعذر تحميل كتالوج الميزات.</p>
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">كتالوج الميزات</h1>
        <p className="text-sm text-stone-600">هذه المفاتيح هي التي يمكن منحها في ملف الترخيص. تعريف ميزة جديدة يتطلب بوابة تنفيذية داخل ERP، لذلك الكتالوج للعرض هنا فقط.</p>
      </div>
      {features.data.length === 0 ? (
        <p className="text-stone-500">لا توجد ميزات معرفة بعد.</p>
      ) : (
        <div className="grid gap-3">
          {features.data.map((feature) => (
            <article className="bg-white border border-stone-200 p-4 rounded" key={feature.id}>
              <div className="flex items-center justify-between gap-3">
                <div>
                  <h2 className="font-semibold">{feature.name_ar}</h2>
                  <code className="text-sm text-stone-600">{feature.key}</code>
                </div>
                <span className={feature.is_active ? 'text-green-700 text-sm' : 'text-stone-500 text-sm'}>
                  {feature.is_active ? 'متاحة للإصدار' : 'موقوفة'}
                </span>
              </div>
              {feature.description_ar ? <p className="text-sm text-stone-600 mt-2">{feature.description_ar}</p> : null}
            </article>
          ))}
        </div>
      )}
    </div>
  )
}
