import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import api, { setStepUpHandler } from '../api/client'
import type { VendorUser } from '../api/types'
import { AuthContext, type AuthContextValue } from './authContext'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<VendorUser | null>(null)
  const [loading, setLoading] = useState(true)
  const [pendingStatus, setPendingStatus] = useState<AuthContextValue['pendingStatus']>(null)

  const refresh = useCallback(async () => {
    try {
      const { data } = await api.get('/api/v1/auth/me')
      setUser(data.user)
      setPendingStatus(null)
    } catch {
      setUser(null)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void refresh()
  }, [refresh])

  const login = useCallback(async (email: string, password: string) => {
    const { data } = await api.post('/api/v1/auth/login', { email, password })
    if (data.status === 'authenticated' && data.user) {
      setUser(data.user)
      setPendingStatus(null)
      return
    }
    setPendingStatus(data.status)
    setUser(null)
  }, [])

  const enrollStart = useCallback(async () => {
    const { data } = await api.post('/api/v1/auth/mfa/enroll/start')
    return { otpauth_uri: data.otpauth_uri as string, secret: data.secret as string }
  }, [])

  const enrollConfirm = useCallback(async (code: string) => {
    const { data } = await api.post('/api/v1/auth/mfa/enroll/confirm', { code })
    setUser(data.user)
    setPendingStatus(null)
    return data.recovery_codes as string[]
  }, [])

  const challenge = useCallback(async (code?: string, recoveryCode?: string) => {
    const { data } = await api.post('/api/v1/auth/mfa/challenge', {
      code: code || undefined,
      recovery_code: recoveryCode || undefined,
    })
    setUser(data.user)
    setPendingStatus(null)
  }, [])

  const stepUp = useCallback(async (code: string) => {
    await api.post('/api/v1/auth/mfa/step-up', { code })
  }, [])

  const logout = useCallback(async () => {
    await api.post('/api/v1/auth/logout')
    setUser(null)
    setPendingStatus(null)
  }, [])

  useEffect(() => {
    setStepUpHandler(async () => {
      // MFA deferred for local build focus; re-enable with CONTROL_CENTER_REQUIRE_MFA=true.
      return true
    })
    return () => setStepUpHandler(null)
  }, [])

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      loading,
      pendingStatus,
      login,
      enrollStart,
      enrollConfirm,
      challenge,
      stepUp,
      logout,
      refresh,
    }),
    [user, loading, pendingStatus, login, enrollStart, enrollConfirm, challenge, stepUp, logout, refresh],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
