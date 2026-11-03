import {
  Alert,
  Badge,
  Button,
  Group,
  NumberInput,
  Paper,
  Skeleton,
  Stack,
  TextInput,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError } from '@/api/errors'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { useCan } from '@/hooks/usePermissions'
import { fetchSettings, updateSettings, type SettingRow } from './api'

type Draft = Record<string, string | number | boolean | null>

const toDraft = (rows: SettingRow[]): Draft => Object.fromEntries(rows.map((r) => [r.key, r.value]))

function SettingsForm({ rows }: { rows: SettingRow[] }) {
  const { t } = useTranslation(['settings', 'common'])
  const queryClient = useQueryClient()
  const canUpdate = useCan('settings.update')
  const [draft, setDraft] = useState<Draft>(() => toDraft(rows))
  const save = useMutation({
    mutationFn: updateSettings,
    onSuccess: (next) => {
      queryClient.setQueryData(['settings', 'all'], next)
      void queryClient.invalidateQueries({ queryKey: ['settings', 'public'] })
    },
  })
  const errors = save.error instanceof ApiError ? save.error.fieldErrors : {}

  const changed = Object.fromEntries(
    rows
      .map((row) => {
        const value = draft[row.key] ?? null
        const normalised = row.type === 'string' && value === '' ? null : value
        return [row.key, normalised] as const
      })
      .filter(([key, value]) => value !== rows.find((r) => r.key === key)?.value),
  )

  const onSave = async () => {
    if (Object.keys(changed).length === 0) {
      notifications.show({ message: t('nothingChanged') })
      return
    }
    try {
      await save.mutateAsync(changed)
      notifications.show({ color: 'teal', message: t('saved') })
    } catch (error) {
      if (!(error instanceof ApiError && error.status === 422))
        notifications.show({ color: 'red', message: (error as Error).message })
    }
  }

  const groups = [...new Set(rows.map((r) => r.key.split('.')[0] ?? ''))]

  return (
    <Stack gap="lg">
      {!canUpdate && (
        <Alert color="cobalt" variant="light">
          {t('readOnly')}
        </Alert>
      )}
      {groups.map((group) => (
        <Paper key={group} withBorder p="lg">
          <Title order={2} size="h4" mb="md">
            {t(`groups.${group}`, { defaultValue: group })}
          </Title>
          <Stack gap="md" maw={560}>
            {rows
              .filter((row) => row.key.startsWith(`${group}.`))
              .map((row) => {
                const label = t(`keys.${row.key}.label`, { defaultValue: row.key })
                const description = (
                  <Group gap={6} component="span">
                    <span>{t(`keys.${row.key}.hint`, { defaultValue: '' })}</span>
                    {row.default !== null && <span>· {t('default', { value: String(row.default) })}</span>}
                    {row.public && (
                      <Badge size="xs" variant="light" color="gray" tt="none" component="span">
                        {t('public')}
                      </Badge>
                    )}
                  </Group>
                )
                const error = errors[`values.${row.key}`]?.[0]
                return row.type === 'integer' ? (
                  <NumberInput
                    key={row.key}
                    label={label}
                    description={description}
                    value={typeof draft[row.key] === 'number' ? (draft[row.key] as number) : ''}
                    onChange={(v) => setDraft((d) => ({ ...d, [row.key]: typeof v === 'number' ? v : null }))}
                    allowDecimal={false}
                    disabled={!canUpdate}
                    error={error}
                    w={220}
                  />
                ) : (
                  <TextInput
                    key={row.key}
                    label={label}
                    description={description}
                    value={(draft[row.key] as string | null) ?? ''}
                    onChange={(e) => {
                      const value = e.currentTarget.value
                      setDraft((d) => ({ ...d, [row.key]: value }))
                    }}
                    disabled={!canUpdate}
                    error={error}
                  />
                )
              })}
          </Stack>
        </Paper>
      ))}
      {canUpdate && (
        <Group justify="flex-end">
          <Button onClick={() => void onSave()} loading={save.isPending}>
            {t('common:actions.save')}
          </Button>
        </Group>
      )}
    </Stack>
  )
}

export function SettingsPage() {
  const { t } = useTranslation('settings')
  const query = useQuery({ queryKey: ['settings', 'all'], queryFn: fetchSettings })

  return (
    <>
      <PageHeader title={t('title')} description={t('description')} />
      {query.isError ? (
        <ErrorState error={query.error} onRetry={() => void query.refetch()} />
      ) : !query.data ? (
        <Skeleton height={360} radius="md" />
      ) : (
        <SettingsForm key={JSON.stringify(query.data)} rows={query.data} />
      )}
    </>
  )
}
