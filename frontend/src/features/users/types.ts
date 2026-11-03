import type { Language } from '@/i18n'

export interface AccessRow {
  organization_id: number
  organization_name?: string
  include_descendants: boolean
}

export interface User {
  id: number
  username: string
  name: string
  email: string | null
  locale: Language
  is_active: boolean
  must_change_password: boolean
  last_login_at: string | null
  organization: { id: number; name: string } | null
  employee: { id: number; full_name: string } | null
  roles: { name: string; display_name: string }[]
  access: AccessRow[]
  created_at: string | null
  can: { update: boolean; deactivate: boolean; reset_password: boolean; manage_access: boolean }
}
