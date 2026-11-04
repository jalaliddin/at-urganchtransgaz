import { Outlet } from 'react-router'
import { ForbiddenPage } from '@/features/errors/ForbiddenPage'
import { useCan } from '@/hooks/usePermissions'

/** Route-level gate (UX only — the API enforces the same permission). A list means any one of them. */
export function RequirePermission({ permission }: { permission: string | string[] }) {
  return useCan(permission, 'any') ? <Outlet /> : <ForbiddenPage />
}
