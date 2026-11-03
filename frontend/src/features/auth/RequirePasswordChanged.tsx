import { Navigate, Outlet } from 'react-router'
import { useMe } from './queries'

/** Mirrors the backend gate: a required or expired password must be changed first. */
export function RequirePasswordChanged() {
  const { data: me } = useMe()

  if (me && (me.must_change_password || me.password_expired)) {
    return <Navigate to="/change-password" replace />
  }

  return <Outlet />
}
