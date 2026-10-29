import { useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../auth/authContext'

const groups = [
  { title: 'إدارة المنتج', links: [ ['/', 'نظرة عامة', '◈', 'overview.view'], ['/customers', 'العملاء', '♧', 'customers.view'], ['/installations', 'التثبيتات', '▣', 'installations.view'], ['/licenses', 'التراخيص', '◇', 'licenses.view'], ['/features', 'كتالوج الميزات', '⊞', 'licenses.view'] ] },
  { title: 'خدمات قادمة', links: [ ['/activations', 'طلبات التهيئة', '↗', ''], ['/support', 'جلسات الدعم', '◎', ''], ['/releases', 'الإصدارات والتحديثات', '↥', ''] ] },
  { title: 'الإدارة والأمان', links: [ ['/audit', 'سجل التدقيق', '≡', 'audit.view'], ['/users', 'مستخدمو اللوحة', '♙', 'users.view'] ] },
]

export function AppShell() {
  const { user, logout } = useAuth()
  const [open, setOpen] = useState(false)
  const [logoutError, setLogoutError] = useState(false)
  const { pathname } = useLocation()
  const current = groups.flatMap(group => group.links).find(link => link[0] === pathname)?.[1] ?? 'مركز التحكم'
  return <div className="app-shell" dir="rtl">
    <a className="skip-link" href="#main-content">انتقل إلى المحتوى</a>
    <aside id="main-navigation" className={`sidebar ${open ? 'sidebar-open' : ''}`}>
      <NavLink to="/" className="brand" onClick={() => setOpen(false)}><img className="brand-mark" src="/logo-mark.png" alt="شعار Pro One" /><span><strong>Pro One</strong><small>مركز التحكم والتراخيص</small></span></NavLink>
      <nav aria-label="التنقل الرئيسي">{groups.map(group => <div className="nav-group" key={group.title}><p className="nav-caption">{group.title}</p>{group.links.filter(link => !link[3] || user?.permissions.includes(link[3])).map(([to, label, icon]) => <NavLink key={to} to={to} end={to === '/'} onClick={() => setOpen(false)} className={({ isActive }) => `nav-link ${isActive ? 'selected' : ''}`}><span className="nav-icon" aria-hidden="true">{icon}</span><span>{label}</span>{group.title === 'خدمات قادمة' && <small className="coming-badge">قريبًا</small>}</NavLink>)}</div>)}</nav>
      <div className="sidebar-footer"><span className="avatar">{user?.name?.slice(0, 1)}</span><div><strong>{user?.name}</strong><small>{user?.role_label}</small></div><button type="button" className="logout-button" aria-label="تسجيل الخروج" onClick={() => { void logout().catch(() => setLogoutError(true)) }}>↪</button></div>
    </aside>
    {open && <button className="sidebar-backdrop" aria-label="إغلاق القائمة" onClick={() => setOpen(false)} />}
    <div className="workspace"><header className="topbar"><div className="topbar-start"><button className="menu-button" aria-label={open ? 'إغلاق القائمة' : 'فتح القائمة'} aria-expanded={open} aria-controls="main-navigation" onClick={() => setOpen(!open)}>☰</button><span className="breadcrumb">مركز التحكم <span>/</span> <strong>{current}</strong></span></div><span className="workspace-label">مساحة إدارة المنتج</span></header><main id="main-content" className="page-content" tabIndex={-1}>{logoutError && <p role="alert" className="text-red-700">تعذر تسجيل الخروج، حاول مجددًا.</p>}<Outlet /></main><footer className="workspace-footer"><span>Pro One · مركز التحكم</span><span>إدارة عملائك وتراخيص منتجك في مكان واحد</span></footer></div>
  </div>
}
