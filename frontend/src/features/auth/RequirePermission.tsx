import { Outlet } from 'react-router'
import { ForbiddenPage } from '@/features/errors/ForbiddenPage'
import { useCan } from '@/hooks/usePermissions'

/** Route-level gate (UX only — the API enforces the same permission). */
export function RequirePermission({ permission }: { permission: string }) {
  return useCan(permission) ? <Outlet /> : <ForbiddenPage />
}
