/** Envelope used by Laravel API Resources. */
export interface DataEnvelope<T> {
  data: T
}

export interface AuthUser {
  id: string
  name: string
  email: string
}

export type CompanyRole = 'owner' | 'admin' | 'receptionist' | 'professional'

/**
 * Capability flags sent by the backend for the current user in one company.
 * Used only to show or hide UI; the API enforces every rule independently.
 */
export interface CompanyPermissions {
  manage_company: boolean
  manage_members: boolean
  manage_customers: boolean
  manage_catalog: boolean
  manage_availability: boolean
  manage_appointments: boolean
  delete_records: boolean
}

export type Permission = keyof CompanyPermissions

export interface Membership {
  role: CompanyRole
  role_label: string
  permissions: CompanyPermissions
}

export interface Company {
  id: string
  name: string
  slug: string
  timezone: string
  currency: string
  locale: string
  membership: Membership | null
}

export interface SessionPayload {
  user: AuthUser
  companies: Company[]
}
