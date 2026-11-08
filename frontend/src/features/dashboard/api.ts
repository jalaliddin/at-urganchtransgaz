import { api } from '@/api/client'
import type { Resource } from '@/api/types'

export interface AuditEntry {
  id: number
  event: string
  event_label: string
  auditable_type: string | null
  auditable_id: number | null
  user: { id: number; username: string } | null
  user_label: string | null
  organization: { id: number; name: string } | null
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  ip_address: string | null
  user_agent: string | null
  http_method: string | null
  url: string | null
  request_id: string | null
  created_at: string | null
}

/** Every key except profile is present only when the user holds its permission. */
export interface DashboardData {
  profile: {
    name: string
    username: string
    organization: { id: number; name: string } | null
    roles: string[]
  }
  organizations?: { total: number; active: number }
  employees?: {
    total: number
    by_status: Record<'active' | 'on_leave' | 'business_trip' | 'dismissed', number>
  }
  it_coverage?: { with_responsible: number; without_responsible: number }
  users?: { total: number; active: number; pending_password_change: number }
  recent_audit?: AuditEntry[]
  helpdesk?: {
    open: number
    overdue: number
    new_today: number
    assigned_to_me: number
    by_status: Record<string, number>
  }
  my_tickets?: { open: number; total: number }
  assets?: { active: number; in_repair: number; warranty_expiring: number; by_status: Record<string, number> }
  my_assets?: { total: number }
  warehouse?: { low_stock: number; expiring: number; documents_month: number }
  requisitions?: { awaiting_action: number; open: number; mine_open: number }
  software?: {
    devices: number
    protected: number
    unprotected: number
    outdated: number
    missing_required: number
    licenses_expiring: number
  }
}

export async function fetchDashboard(organizationId: number | null): Promise<DashboardData> {
  const { data } = await api.get<Resource<DashboardData>>('/dashboard', {
    params: organizationId ? { organization_id: organizationId } : {},
  })
  return data.data
}
