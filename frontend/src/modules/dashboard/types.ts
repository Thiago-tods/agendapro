import type { Appointment } from '@/types/api'

export interface PeriodMetrics {
  booked: number
  completed: number
  no_show: number
  cancelled: number
  /** completed / (completed + no_show); null when no outcome is known yet. */
  attendance_rate: number | null
  /** Null when the user may not see financial data. */
  revenue_cents: number | null
}

export interface WeeklyPoint {
  week_start: string
  booked: number
  completed: number
  revenue_cents: number | null
}

export interface ActivityItem {
  id: string
  type: string
  label: string
  reason: string | null
  actor: string | null
  created_at: string
  appointment: { id: string; customer: string; service: string; local_start: string }
}

export interface RankedService {
  id: string
  name: string
  color: string
  completed: number
  revenue_cents: number | null
}

export interface RankedProfessional {
  id: string
  name: string
  color: string
  completed: number
  no_show: number
  attendance_rate: number
}

export interface Dashboard {
  timezone: string
  generated_at: string
  today: {
    total: number
    remaining: number
    in_progress: number
    completed: number
    no_show: number
    cancelled: number
    awaiting_confirmation: number
  }
  period: { days: number; from: string; to: string; current: PeriodMetrics; previous: PeriodMetrics }
  weekly_trend: WeeklyPoint[]
  upcoming: Appointment[]
  recent_activity: ActivityItem[]
  top_services: RankedService[]
  top_professionals: RankedProfessional[]
}
