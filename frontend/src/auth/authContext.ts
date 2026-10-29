import { createContext, useContext } from 'react'
import type { VendorUser } from '../api/types'

export type AuthContextValue = {
  user: VendorUser | null
  loading: boolean
  pendingStatus: 'mfa_required' | 'mfa_enrollment_required' | null
  login: (email: string, password: string) => Promise<void>
  enrollStart: () => Promise<{ otpauth_uri: string; secret: string }>
  enrollConfirm: (code: string) => Promise<string[]>
  challenge: (code?: string, recoveryCode?: string) => Promise<void>
  stepUp: (code: string) => Promise<void>
  logout: () => Promise<void>
  refresh: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)

export function useAuth(): AuthContextValue {
  const value = useContext(AuthContext)
  if (!value) {
    throw new Error('useAuth must be used within AuthProvider')
  }
  return value
}
