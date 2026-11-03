import { useMe } from '@/features/auth/queries'
import type { Me } from '@/features/auth/types'

export type PermissionMode = 'all' | 'any'

export function hasPermission(
  me: Me | null | undefined,
  permission: string | string[],
  mode: PermissionMode = 'all',
): boolean {
  if (!me) return false
  if (me.is_super_admin) return true
  const required = Array.isArray(permission) ? permission : [permission]
  return mode === 'all'
    ? required.every((p) => me.permissions.includes(p))
    : required.some((p) => me.permissions.includes(p))
}

/**
 * Hides UI the user cannot use. The backend still checks every request — this is
 * convenience, not security.
 */
export function useCan(permission: string | string[], mode: PermissionMode = 'all'): boolean {
  const { data: me } = useMe()
  return hasPermission(me, permission, mode)
}
