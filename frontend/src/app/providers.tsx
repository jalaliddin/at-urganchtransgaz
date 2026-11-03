import { localStorageColorSchemeManager, MantineProvider } from '@mantine/core'
import { DatesProvider } from '@mantine/dates'
import { ModalsProvider } from '@mantine/modals'
import { Notifications } from '@mantine/notifications'
import { QueryClientProvider, type QueryClient } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { DAYJS_LOCALES } from '@/lib/dayjs'
import { isLanguage } from '@/i18n'
import { theme } from './theme'

const colorSchemeManager = localStorageColorSchemeManager({ key: 'at.color-scheme' })

export function AppProviders({ queryClient, children }: { queryClient: QueryClient; children: ReactNode }) {
  const { i18n } = useTranslation()
  const language = isLanguage(i18n.language) ? i18n.language : 'uz'

  return (
    <MantineProvider theme={theme} defaultColorScheme="light" colorSchemeManager={colorSchemeManager}>
      <DatesProvider settings={{ locale: DAYJS_LOCALES[language], firstDayOfWeek: 1 }}>
        <ModalsProvider>
          <Notifications position="top-right" />
          <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
        </ModalsProvider>
      </DatesProvider>
    </MantineProvider>
  )
}
