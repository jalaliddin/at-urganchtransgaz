import { Stack, Text, Title } from '@mantine/core'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { GirihPattern } from '@/components/GirihPattern'
import { usePublicSettings } from '@/features/auth/queries'
import classes from './AuthLayout.module.css'

interface Props {
  title: string
  description?: ReactNode
  children: ReactNode
}

/** Sign-in family of pages: girih brand panel + a single focused form. */
export function AuthLayout({ title, description, children }: Props) {
  const { t } = useTranslation()
  const { data: settings } = usePublicSettings()
  const hasSupport = Boolean(settings?.support_phone || settings?.support_email)

  return (
    <div className={classes.root}>
      <aside className={classes.brand}>
        <GirihPattern className={classes.pattern} />
        <div className={classes.cartouche}>
          <div className={classes.systemName}>{settings?.system_name ?? t('app.name')}</div>
          <div className={classes.tagline}>{t('app.fullName')}</div>
          {settings?.organization_name && (
            <div className={classes.organization}>{settings.organization_name}</div>
          )}
        </div>
      </aside>

      <main className={classes.main}>
        <Stack className={classes.form} gap="lg">
          <Stack gap={6}>
            <Title order={1}>{title}</Title>
            {description && (
              <Text c="dimmed" size="sm">
                {description}
              </Text>
            )}
          </Stack>

          {children}

          {hasSupport && (
            <Stack gap={2} pt="md">
              <Text size="sm" fw={500}>
                {t('auth:support.title')}
              </Text>
              {settings?.support_phone && (
                <Text size="sm" c="dimmed">
                  {t('auth:support.phone', { value: settings.support_phone })}
                </Text>
              )}
              {settings?.support_email && (
                <Text size="sm" c="dimmed">
                  {t('auth:support.email', { value: settings.support_email })}
                </Text>
              )}
            </Stack>
          )}
        </Stack>
      </main>
    </div>
  )
}
