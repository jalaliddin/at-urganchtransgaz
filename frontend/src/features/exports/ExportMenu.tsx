import { Button, Menu } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconChevronDown, IconFileSpreadsheet, IconFileTypePdf, IconDownload } from '@tabler/icons-react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { toApiError } from '@/api/errors'
import { requestExport, type ExportFormat } from './api'

interface Props {
  type: string
  /** Current list filters (filter[...] names without the wrapper) and search. */
  filters: Record<string, string>
}

/** Queues an Excel/PDF export of the current list; the file arrives on the exports page. */
export function ExportMenu({ type, filters }: Props) {
  const { t } = useTranslation(['helpdesk', 'exports'])
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const mutation = useMutation({
    mutationFn: (format: ExportFormat) => requestExport(type, format, filters),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['exports'] })
      notifications.show({
        color: 'cobalt',
        message: t('exports:queued'),
        onClick: () => navigate('/exports'),
        style: { cursor: 'pointer' },
      })
    },
    onError: (error) => notifications.show({ color: 'red', message: toApiError(error).message }),
  })

  return (
    <Menu position="bottom-end" withinPortal>
      <Menu.Target>
        <Button
          variant="default"
          leftSection={<IconDownload size={16} />}
          rightSection={<IconChevronDown size={14} />}
          loading={mutation.isPending}
        >
          {t('helpdesk:actions.export')}
        </Button>
      </Menu.Target>
      <Menu.Dropdown>
        <Menu.Item leftSection={<IconFileSpreadsheet size={16} />} onClick={() => mutation.mutate('xlsx')}>
          {t('helpdesk:actions.exportXlsx')}
        </Menu.Item>
        <Menu.Item leftSection={<IconFileTypePdf size={16} />} onClick={() => mutation.mutate('pdf')}>
          {t('helpdesk:actions.exportPdf')}
        </Menu.Item>
      </Menu.Dropdown>
    </Menu>
  )
}
