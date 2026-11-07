import { Checkbox, Paper, Stack, Text, Title } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { updateMailMuted } from '@/features/auth/api'
import { meQueryKey } from '@/features/auth/queries'
import type { Me } from '@/features/auth/types'

/** Types mirror the server ChannelResolver::TYPES. */
export const MAIL_TYPES = [
  'ticket_created',
  'ticket_assigned',
  'ticket_status_changed',
  'ticket_commented',
  'ticket_sla_breached',
  'ticket_due_soon',
  'export_ready',
  'assets_return_required',
  'asset_warranty_expiring',
  'asset_transfer_incoming',
  'asset_disposal_requested',
] as const

/** In-app notifications always arrive; here the user chooses which ones also come by e-mail. */
export function NotificationPreferences({ me }: { me: Me }) {
  const { t } = useTranslation('profile')
  const queryClient = useQueryClient()
  const mutation = useMutation({
    mutationFn: updateMailMuted,
    onSuccess: (updated) => queryClient.setQueryData(meQueryKey, updated),
    onError: (error) => notifications.show({ color: 'red', message: toApiError(error).message }),
  })
  const muted = me.notification_preferences.mail_muted

  return (
    <Paper withBorder p="lg">
      <Title order={2} size="h4" mb="xs">
        {t('notifications.title')}
      </Title>
      <Text size="sm" c="dimmed" mb="md">
        {me.email ? t('notifications.hint') : t('notifications.noEmail')}
      </Text>
      <Stack gap="xs">
        {MAIL_TYPES.map((type) => (
          <Checkbox
            key={type}
            label={t(`notifications.types.${type}`)}
            checked={!muted.includes(type)}
            disabled={!me.email || mutation.isPending}
            onChange={(e) =>
              mutation.mutate(e.currentTarget.checked ? muted.filter((m) => m !== type) : [...muted, type])
            }
          />
        ))}
      </Stack>
    </Paper>
  )
}
