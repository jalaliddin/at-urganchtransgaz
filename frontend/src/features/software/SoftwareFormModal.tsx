import { Button, Checkbox, Group, Modal, Select, SimpleGrid, Stack, TextInput, Textarea } from '@mantine/core'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import * as s from './api'
import { useSoftwareMutation } from './queries'
import { LICENSE_TYPES, SOFTWARE_CATEGORIES, type Software } from './types'

/** Create or edit a catalog title. */
export function SoftwareFormModal({
  software,
  initial,
  onClose,
}: {
  software?: Software
  initial?: { name: string; vendor: string | null }
  onClose: (saved?: Software) => void
}) {
  const { t } = useTranslation(['software', 'common', 'validation'])
  const [name, setName] = useState(software?.name ?? initial?.name ?? '')
  const [vendor, setVendor] = useState(software?.vendor ?? initial?.vendor ?? '')
  const [category, setCategory] = useState<string | null>(software?.category ?? 'other')
  const [licenseType, setLicenseType] = useState<string | null>(software?.license_type ?? 'commercial')
  const [antivirus, setAntivirus] = useState(software?.is_antivirus ?? false)
  const [active, setActive] = useState(software?.is_active ?? true)
  const [description, setDescription] = useState(software?.description ?? '')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useSoftwareMutation(() =>
    s.saveSoftware(
      {
        name,
        vendor: vendor || null,
        category,
        license_type: licenseType,
        is_antivirus: antivirus,
        is_active: active,
        description: description || null,
      },
      software?.id,
    ),
  )

  return (
    <Modal opened onClose={() => onClose()} title={software ? t('catalog.edit') : t('catalog.add')} centered>
      <Stack>
        <TextInput
          label={t('fields.name')}
          required
          value={name}
          onChange={(e) => setName(e.currentTarget.value)}
          error={errors.name}
        />
        <TextInput
          label={t('fields.vendor')}
          value={vendor}
          onChange={(e) => setVendor(e.currentTarget.value)}
        />
        <SimpleGrid cols={2}>
          <Select
            label={t('fields.category')}
            required
            allowDeselect={false}
            data={SOFTWARE_CATEGORIES.map((c) => ({ value: c, label: t(`categories.${c}`) }))}
            value={category}
            onChange={(v) => {
              setCategory(v)
              if (v === 'antivirus') setAntivirus(true)
            }}
          />
          <Select
            label={t('fields.licenseType')}
            required
            allowDeselect={false}
            data={LICENSE_TYPES.map((c) => ({ value: c, label: t(`licenseTypes.${c}`) }))}
            value={licenseType}
            onChange={setLicenseType}
          />
        </SimpleGrid>
        <Checkbox
          label={t('catalog.isAntivirus')}
          checked={antivirus}
          onChange={(e) => setAntivirus(e.currentTarget.checked)}
        />
        <Checkbox
          label={t('catalog.active')}
          checked={active}
          onChange={(e) => setActive(e.currentTarget.checked)}
        />
        <Textarea
          label={t('fields.description')}
          autosize
          minRows={2}
          value={description}
          onChange={(e) => setDescription(e.currentTarget.value)}
        />
        <Group justify="flex-end">
          <Button
            loading={save.isPending}
            onClick={() => {
              if (!name.trim()) {
                setErrors({ name: t('validation:required') })
                return
              }
              save.mutate(undefined, {
                onSuccess: (saved) => onClose(saved),
                onError: (e) =>
                  setErrors(
                    Object.fromEntries(
                      Object.entries(toApiError(e).fieldErrors).map(([k, v]) => [k, v[0] ?? '']),
                    ),
                  ),
              })
            }}
          >
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
