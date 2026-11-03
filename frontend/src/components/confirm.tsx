import { Text } from '@mantine/core'
import { modals } from '@mantine/modals'
import i18n from '@/i18n'

interface ConfirmOptions {
  title?: string
  message: string
  confirmLabel: string
  danger?: boolean
}

/** Explicit confirmation for destructive or state-changing actions. Resolves true on confirm. */
export function confirmAction({
  title,
  message,
  confirmLabel,
  danger = false,
}: ConfirmOptions): Promise<boolean> {
  return new Promise((resolve) => {
    let settled = false
    const settle = (value: boolean) => {
      if (!settled) {
        settled = true
        resolve(value)
      }
    }
    modals.openConfirmModal({
      title: title ?? i18n.t('common:confirm.title'),
      children: <Text size="sm">{message}</Text>,
      labels: { confirm: confirmLabel, cancel: i18n.t('common:actions.cancel') },
      confirmProps: { color: danger ? 'red' : undefined },
      onConfirm: () => settle(true),
      onCancel: () => settle(false),
      onClose: () => settle(false),
    })
  })
}
