import {
  Button,
  FileInput,
  Group,
  Modal,
  Select,
  SimpleGrid,
  Stack,
  Textarea,
  TextInput,
} from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { IconPaperclip } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError, toApiError } from '@/api/errors'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { useMe } from '@/features/auth/queries'
import { DOCUMENT_TYPES, type DocumentRecord, type DocumentType } from './api'
import { useDocumentMutations } from './queries'

interface Props {
  document?: DocumentRecord
  onClose: (saved?: DocumentRecord) => void
}

export function DocumentFormModal({ document, onClose }: Props) {
  const { t } = useTranslation(['documents', 'common', 'validation'])
  const { data: me } = useMe()
  const { create, update } = useDocumentMutations(document?.id)
  const [organizationId, setOrganizationId] = useState<number | null>(
    document?.organization?.id ?? me?.organization?.id ?? null,
  )
  const [type, setType] = useState<DocumentType>(document?.type ?? 'contract')
  const [number, setNumber] = useState(document?.number ?? '')
  const [title, setTitle] = useState(document?.title ?? '')
  const [date, setDate] = useState<string | null>(document?.document_date ?? null)
  const [description, setDescription] = useState(document?.description ?? '')
  const [file, setFile] = useState<File | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const pending = create.isPending || update.isPending

  const submit = async () => {
    const local: Record<string, string> = {}
    if (title.trim() === '') local.title = t('validation:required')
    if (organizationId === null) local.organization_id = t('validation:required')
    setErrors(local)
    if (Object.keys(local).length > 0 || organizationId === null) return
    const input = {
      organization_id: organizationId,
      type,
      number: number.trim() || null,
      title: title.trim(),
      document_date: date,
      description: description.trim() || null,
    }
    try {
      const saved = document ? await update.mutateAsync(input) : await create.mutateAsync({ input, file })
      onClose(saved)
    } catch (error) {
      const apiError = toApiError(error)
      setErrors(
        error instanceof ApiError && error.status === 422
          ? Object.fromEntries(Object.entries(apiError.fieldErrors).map(([k, v]) => [k, v[0] ?? '']))
          : { general: apiError.message },
      )
    }
  }

  return (
    <Modal
      opened
      onClose={() => onClose()}
      title={t(document ? 'actions.edit' : 'actions.create')}
      size="lg"
      centered
    >
      <Stack>
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          <OrganizationSelect
            label={t('fields.organization')}
            required
            onlyActive
            value={organizationId}
            onChange={setOrganizationId}
            error={errors.organization_id}
            disabled={Boolean(document)}
          />
          <Select
            label={t('fields.type')}
            required
            data={DOCUMENT_TYPES.map((v) => ({ value: v, label: t(`types.${v}`) }))}
            value={type}
            onChange={(v) => setType((v as DocumentType) ?? 'other')}
            allowDeselect={false}
            error={errors.type}
          />
          <TextInput
            label={t('fields.number')}
            value={number}
            onChange={(e) => setNumber(e.currentTarget.value)}
            error={errors.number}
          />
          <DateInput
            label={t('fields.date')}
            value={date}
            onChange={(v) => setDate(v)}
            valueFormat="DD.MM.YYYY"
            clearable
            error={errors.document_date}
          />
        </SimpleGrid>
        <TextInput
          label={t('fields.title')}
          required
          value={title}
          onChange={(e) => setTitle(e.currentTarget.value)}
          error={errors.title}
        />
        <Textarea
          label={t('fields.description')}
          autosize
          minRows={2}
          value={description}
          onChange={(e) => setDescription(e.currentTarget.value)}
        />
        {!document && (
          <FileInput
            label={t('fields.file')}
            leftSection={<IconPaperclip size={16} />}
            value={file}
            onChange={setFile}
            clearable
            error={errors.file ?? errors.general}
          />
        )}
        <Group justify="flex-end">
          <Button onClick={() => void submit()} loading={pending}>
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
