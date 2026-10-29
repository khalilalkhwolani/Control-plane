import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import api from '../api/client'
import type { Overview } from '../api/types'
import { useAuth } from '../auth/authContext'

export function OverviewPage() {
  const { user } = useAuth()
  const query = useQuery({ queryKey: ['overview'], queryFn: async () => (await api.get<{ data: Overview }>('/api/v1/overview')).data.data })
  const data = query.data
  if (query.isPending) return <div className="loading-panel" role="status">جاري تحميل ملخص النشاط…</div>
  if (query.isError || !data) return <div className="empty-panel" role="alert"><h2>تعذر تحميل الملخص</h2><p>تحقق من الاتصال ثم حاول مجددًا.</p><button onClick={() => void query.refetch()}>إعادة المحاولة</button></div>
  const steps = [
    { to: '/customers', title: 'سجّل العميل', body: 'بيانات المنشأة وحالة العقد وجهة الاتصال.', permission: 'customers.view' },
    { to: '/installations', title: 'اعتمد التثبيت', body: 'اربط هوية تثبيت النظام بالعميل الصحيح.', permission: 'installations.view' },
    { to: '/licenses', title: 'أصدر الترخيص', body: 'حدد الميزات والمدة ونزّل الملف الموقّع.', permission: 'licenses.view' },
  ]
  return <div className="space-y-6">
    <div className="page-heading"><div><p className="eyebrow">مساحة العمل</p><h1>نظرة عامة</h1><p>أهلًا {user?.name}، تابع عملاءك والتثبيتات والتراخيص الصادرة.</p></div>{user?.permissions.includes('licenses.issue') && <Link className="primary-link" to="/licenses">＋ إصدار ترخيص</Link>}</div>
    <div className="stats-grid"><Stat label="العملاء المسجلون" value={data.customers_count} icon="♧" hint="سجل عملاء المنتج" /><Stat label="تثبيتات النظام" value={data.installations_count} icon="▣" hint="جميع المواقع المسجلة" /><Stat label="بانتظار الاعتماد" value={data.pending_activations} icon="◷" hint="تثبيتات تحتاج مراجعتك" warn /><Stat label="التراخيص الصادرة" value={data.licenses_count} icon="◇" hint="يشمل إصدارات التجديد السابقة" /></div>
    <section className="workflow-panel"><div className="section-heading"><h2>رحلة ترخيص العميل</h2><span className="muted">ثلاث خطوات واضحة</span></div><div className="workflow-grid">{steps.filter(step => user?.permissions.includes(step.permission)).map((step, index) => <Link to={step.to} className="workflow-step" key={step.to}><span className="step-number">0{index + 1}</span><div><h3>{step.title}</h3><p>{step.body}</p></div><span aria-hidden="true">←</span></Link>)}</div></section>
    <div className="attention-strip"><span className="attention-icon" aria-hidden="true">◷</span><div><strong>{data.expiring_licenses > 0 ? `${data.expiring_licenses} ترخيصًا يقترب من الانتهاء` : 'لا توجد تراخيص قريبة الانتهاء'}</strong><p>راجع تواريخ التراخيص ورتّب التجديد مع العملاء.</p></div>{user?.permissions.includes('licenses.view') && <Link to="/licenses">مراجعة التراخيص ←</Link>}</div>
    <section className="activity-panel"><div className="section-heading"><div><h2>آخر النشاط</h2><p>آخر العمليات المسجلة في مركز التحكم</p></div>{user?.permissions.includes('audit.view') && <Link to="/audit">عرض السجل ←</Link>}</div>{data.recent_audit.length === 0 ? <div className="empty-panel"><span className="empty-symbol" aria-hidden="true">≡</span><h3>لم يسجّل أي نشاط بعد</h3><p>ستظهر هنا عمليات العملاء والتثبيتات والتراخيص.</p></div> : <div className="table-scroll"><table><thead><tr><th>العملية</th><th>المنفذ</th><th>المستوى</th><th>التاريخ</th></tr></thead><tbody>{data.recent_audit.map(row => <tr key={row.id}><td><code>{row.action}</code></td><td dir="ltr">{row.actor_email ?? 'النظام'}</td><td><span className={`status-badge risk-${row.risk}`}>{({ high: 'مرتفع', medium: 'متوسط', low: 'منخفض' } as Record<string, string>)[row.risk] ?? row.risk}</span></td><td>{new Date(row.created_at).toLocaleString('ar', { dateStyle: 'medium', timeStyle: 'short' })}</td></tr>)}</tbody></table></div>}</section>
  </div>
}
function Stat({ label, value, icon, hint, warn = false }: { label: string; value: number; icon: string; hint: string; warn?: boolean }) {
  return <article className="stat-card"><div className="stat-top"><span>{label}</span><span className={`stat-icon ${warn ? 'warn' : ''}`} aria-hidden="true">{icon}</span></div><strong className="stat-value">{value.toLocaleString('ar')}</strong><small>{hint}</small></article>
}
