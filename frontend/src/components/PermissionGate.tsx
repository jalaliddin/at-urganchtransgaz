import type { ReactNode } from 'react'
import { useCan, type PermissionMode } from '@/hooks/usePermissions'

interface Props {
  permission: string | string[]
  mode?: PermissionMode
  fallback?: ReactNode
  children: ReactNode
}

export function PermissionGate({ permission, mode = 'all', fallback = null, children }: Props) {
  return useCan(permission, mode) ? <>{children}</> : <>{fallback}</>
}
