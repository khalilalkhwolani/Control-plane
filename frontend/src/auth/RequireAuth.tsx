import { Navigate, useLocation } from 'react-router-dom'
import type { ReactNode } from 'react'
import { useAuth } from '../auth/authContext'

export function RequireAuth({ children }: { children: ReactNode }) {
  const { user, loading, pendingStatus } = useAuth()
  const location = useLocation()

  if (loading) {
    return <p className="p-8">جاري التحميل...</p>
  }
  if (pendingStatus === 'mfa_enrollment_required') {
    return <Navigate to="/mfa/enroll" replace />
  }
  if (pendingStatus === 'mfa_required') {
    return <Navigate to="/mfa/challenge" replace />
  }
  if (!user) {
    return <Navigate to="/login" replace state={{ from: location }} />
  }
  return children
}
