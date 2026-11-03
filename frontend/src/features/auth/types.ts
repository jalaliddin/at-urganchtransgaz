import type { Language } from '@/i18n'

export interface Me {
  id: number
  username: string
  name: string
  email: string | null
  locale: Language
  must_change_password: boolean
  password_expired: boolean
  is_super_admin: boolean
  organization: { id: number; name: string } | null
  employee: { id: number; full_name: string; position: string | null } | null
  roles: { name: string; display_name: string }[]
  permissions: string[]
  access: { organization_id: number; organization_name: string; include_descendants: boolean }[]
}

export interface PublicSettings {
  system_name: string
  organization_name: string
  support_phone: string | null
  support_email: string | null
}

export interface LoginInput {
  username: string
  password: string
  remember: boolean
}

export interface ChangePasswordInput {
  current_password: string
  password: string
  password_confirmation: string
}

export interface ResetPasswordInput {
  token: string
  email: string
  password: string
  password_confirmation: string
}
