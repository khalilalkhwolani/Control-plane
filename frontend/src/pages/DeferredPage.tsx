export function DeferredPage({ title, body }: { title: string; body: string }) {
  return (
    <div className="deferred-panel space-y-3">
      <span className="empty-symbol" aria-hidden="true">◇</span>
      <span className="coming-badge">ضمن التطوير القادم</span>
      <h1 className="text-2xl font-semibold">{title}</h1>
      <p className="text-stone-700">{body}</p>
      <p className="text-sm text-stone-500">هذه الخدمة غير متاحة حاليًا. يمكنك متابعة إدارة العملاء والتثبيتات والتراخيص من القائمة.</p>
    </div>
  )
}
