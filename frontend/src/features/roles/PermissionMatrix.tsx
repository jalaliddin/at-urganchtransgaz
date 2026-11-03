import { Checkbox, Group, Paper, SimpleGrid, Stack, Text } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import type { PermissionModule } from './types'

interface Props {
  modules: PermissionModule[]
  value: string[]
  onChange: (value: string[]) => void
  disabled?: boolean
}

/** Permissions grouped by module; a module's header checkbox selects or clears all of it. */
export function PermissionMatrix({ modules, value, onChange, disabled = false }: Props) {
  const { t } = useTranslation('permissions')
  const selected = new Set(value)

  const setModule = (permissions: string[], on: boolean) => {
    const next = new Set(selected)
    for (const p of permissions) {
      if (on) next.add(p)
      else next.delete(p)
    }
    onChange([...next])
  }

  const toggle = (permission: string, on: boolean) => setModule([permission], on)

  return (
    <Stack gap="sm">
      {modules.map(({ module, permissions }) => {
        const count = permissions.filter((p) => selected.has(p)).length
        const moduleLabel = t(`modules.${module}`, { defaultValue: module })
        return (
          <Paper key={module} withBorder p="md">
            <Group justify="space-between" mb="sm">
              <Checkbox
                label={
                  <Text fw={600} size="sm">
                    {moduleLabel}
                  </Text>
                }
                aria-label={moduleLabel}
                checked={count === permissions.length}
                indeterminate={count > 0 && count < permissions.length}
                onChange={(e) => setModule(permissions, e.currentTarget.checked)}
                disabled={disabled}
              />
              <Text size="xs" c="dimmed">
                {count} / {permissions.length}
              </Text>
            </Group>
            <SimpleGrid cols={{ base: 1, xs: 2, md: 3 }} spacing="xs">
              {permissions.map((permission) => {
                const action = permission.slice(permission.indexOf('.') + 1)
                const actionLabel = t(`actions.${action}`, { defaultValue: action })
                return (
                  <Checkbox
                    key={permission}
                    label={actionLabel}
                    aria-label={`${moduleLabel}: ${actionLabel}`}
                    checked={selected.has(permission)}
                    onChange={(e) => toggle(permission, e.currentTarget.checked)}
                    disabled={disabled}
                  />
                )
              })}
            </SimpleGrid>
          </Paper>
        )
      })}
    </Stack>
  )
}
