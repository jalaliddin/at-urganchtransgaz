import { Center, Loader } from '@mantine/core'
import { useEffect } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router'
import i18n, { setLanguage } from '@/i18n'
import { useMe } from './queries'

/** Gate for every signed-in page: no session → /login?redirect=<current page>. */
export function RequireAuth() {
  const { data: me, isPending } = useMe()
  const location = useLocation()

  useEffect(() => {
    if (me && me.locale !== i18n.language) void setLanguage(me.locale)
  }, [me])

  if (isPending) {
    return (
      <Center h="100dvh">
        <Loader aria-label={i18n.t('common:states.loading')} />
      </Center>
    )
  }

  if (!me) {
    const redirect = `${location.pathname}${location.search}`
    return <Navigate to={`/login?redirect=${encodeURIComponent(redirect)}`} replace />
  }

  return <Outlet />
}
