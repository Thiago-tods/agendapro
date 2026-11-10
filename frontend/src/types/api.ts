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
  view_financials: boolean
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

/** Laravel paginated resource collection. */
export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type AppointmentStatus = 'scheduled' | 'confirmed' | 'checked_in' | 'completed' | 'cancelled' | 'no_show'

export interface Appointment {
  id: string
  status: AppointmentStatus
  status_label: string
  starts_at: string
  ends_at: string
  occupied_until: string
  local: { timezone: string; date: string; start: string; end: string }
  customer?: { id: string; name: string; phone: string | null }
  professional?: { id: string; name: string; color: string }
  service: { id: string; name: string; price_cents: number; duration_minutes: number; buffer_minutes: number }
  notes: string | null
}

export interface CustomerSummary {
  id: string
  name: string
  email: string | null
  phone: string | null
}

export interface ProfessionalSummary {
  id: string
  name: string
  color: string
  is_active: boolean
}

export interface ServiceSummary {
  id: string
  name: string
  category: string | null
  price_cents: number
  duration_minutes: number
  buffer_minutes: number
  color: string
  is_active: boolean
}

export interface SessionPayload {
  user: AuthUser
  companies: Company[]
}
