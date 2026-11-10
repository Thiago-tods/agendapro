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

export type AppointmentAction = 'confirm' | 'check_in' | 'complete' | 'no_show' | 'cancel' | 'reschedule'

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
  /** What the current state and time allow, computed by the API. */
  available_actions?: AppointmentAction[]
  checked_in_at?: string | null
  completed_at?: string | null
  cancelled_at?: string | null
  cancellation_reason?: string | null
  events?: AppointmentEvent[]
}

export interface AppointmentEvent {
  id: string
  type: string
  label: string
  from_status: AppointmentStatus | null
  to_status: AppointmentStatus | null
  reason: string | null
  changes: Record<string, unknown> | null
  actor: { id: string; name: string } | null
  created_at: string
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

export interface Customer extends CustomerSummary {
  birth_date: string | null
  notes: string | null
  is_archived: boolean
  archived_at: string | null
  visits_count?: number
  last_visit_at?: string | null
  next_appointment_at?: string | null
  created_at: string
}

export interface Professional extends ProfessionalSummary {
  title: string | null
  bio: string | null
  email: string | null
  phone: string | null
  services_count?: number
  services?: Array<{ id: string; name: string; color: string; is_active: boolean }>
}

export interface Service extends ServiceSummary {
  description: string | null
  professionals_count?: number
  professionals?: ProfessionalSummary[]
}

export interface WeeklyPeriod {
  weekday: number
  start_time: string
  end_time: string
}

export interface WeeklyHours {
  timezone: string
  uses_company_hours: boolean
  periods: WeeklyPeriod[]
}

export interface CompanyMember {
  id: string
  role: CompanyRole
  role_label: string
  user: AuthUser
  joined_at: string
}
