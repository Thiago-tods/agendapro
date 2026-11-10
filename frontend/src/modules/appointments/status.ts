import type { AppointmentStatus } from '@/types/api'

type Tone = 'neutral' | 'primary' | 'success' | 'warning' | 'danger' | 'info'

/** One meaning per color across the app; the label always accompanies it. */
export const STATUS_TONE: Record<AppointmentStatus, Tone> = {
  scheduled: 'warning',
  confirmed: 'info',
  checked_in: 'primary',
  completed: 'success',
  cancelled: 'neutral',
  no_show: 'danger',
}
