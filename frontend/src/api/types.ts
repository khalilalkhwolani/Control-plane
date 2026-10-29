export type AuthStatus = 'mfa_required' | 'mfa_enrollment_required'

export type VendorUser = {
  id: number
  name: string
  email: string
  role: string
  role_label: string
  is_active: boolean
  mfa_enrolled: boolean
  last_login_at: string | null
  permissions: string[]
}

export type Paginated<T> = {
  success: boolean
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export type Customer = {
  id: number
  reference: string
  legal_name: string
  display_name: string
  contract_state: string
  notes: string | null
  contacts?: Array<{
    id?: number
    name: string
    email: string | null
    phone: string | null
    is_primary: boolean
  }>
  installations_count?: number
}

export type Installation = {
  id: number
  customer_id: number
  customer?: { id: number; reference: string; display_name: string }
  installation_uuid: string
  site_label: string
  deployment_mode: 'offline' | 'connected'
  activation_state: 'pending' | 'approved' | 'suspended'
  notes: string | null
}

export type AuditEvent = {
  id: number
  action: string
  actor_email: string | null
  risk: string
  request_id: string
  created_at: string
  reason: string | null
}

export type FeatureDefinition = {
  id: number
  key: string
  name_ar: string
  description_ar: string | null
  is_active: boolean
}

export type LicenseRevision = {
  id: number
  installation_id: number
  installation?: {
    id: number
    installation_uuid: string
    site_label: string
    customer: { id: number; reference: string; display_name: string } | null
  }
  license_uuid: string
  sequence: number
  key_id: string
  edition: string
  features: string[]
  issued_at: string | null
  expires_at: string | null
  grace_until: string | null
  reason: string
  issuer?: { id: number; name: string; email: string } | null
  created_at: string | null
}

export type Overview = {
  customers_count: number
  installations_count: number
  pending_activations: number
  licenses_count: number
  expiring_licenses: number
  unacknowledged_files: number
  open_support_sessions: number
  security_alerts: number
  recent_audit: AuditEvent[]
  deferred: Record<string, string>
}
