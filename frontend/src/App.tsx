import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './auth/AuthProvider'
import { RequireAuth } from './auth/RequireAuth'
import { AppShell } from './layout/AppShell'
import { LoginPage } from './pages/LoginPage'
import { MfaEnrollPage } from './pages/MfaEnrollPage'
import { MfaChallengePage } from './pages/MfaChallengePage'
import { OverviewPage } from './pages/OverviewPage'
import { CustomersPage } from './pages/CustomersPage'
import { InstallationsPage } from './pages/InstallationsPage'
import { UsersPage } from './pages/UsersPage'
import { AuditPage } from './pages/AuditPage'
import { FeaturesPage } from './pages/FeaturesPage'
import { LicensesPage } from './pages/LicensesPage'
import { DeferredPage } from './pages/DeferredPage'

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/mfa/enroll" element={<MfaEnrollPage />} />
          <Route path="/mfa/challenge" element={<MfaChallengePage />} />
          <Route
            path="/"
            element={(
              <RequireAuth>
                <AppShell />
              </RequireAuth>
            )}
          >
            <Route index element={<OverviewPage />} />
            <Route path="customers" element={<CustomersPage />} />
            <Route path="installations" element={<InstallationsPage />} />
            <Route path="users" element={<UsersPage />} />
            <Route path="audit" element={<AuditPage />} />
            <Route path="licenses" element={<LicensesPage />} />
            <Route path="features" element={<FeaturesPage />} />
            <Route path="activations" element={<DeferredPage title="طلبات التهيئة" body="رمز التهيئة لمرة واحدة مرحلة لاحقة. التثبيتات تُسجَّل يدوياً الآن." />} />
            <Route path="support" element={<DeferredPage title="جلسات الدعم" body="الدعم المؤقت الظاهر للعميل مؤجّل. لا يوجد حساب مورّد خفي." />} />
            <Route path="releases" element={<DeferredPage title="الإصدارات والتحديثات" body="حزم الإنتاج الموقعة ومسار التغليف مرحلة لاحقة." />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}
