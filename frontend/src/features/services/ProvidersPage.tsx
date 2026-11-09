import { Badge } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { CatalogTable } from '@/components/CatalogTable'
import { PageHeader } from '@/components/PageHeader'
import { useCan } from '@/hooks/usePermissions'
import * as s from './api'
import { useProviders } from './queries'

/** Operators and providers shared by SIM cards, contracts and services. */
export function ProvidersPage() {
  const { t } = useTranslation(['services', 'common'])
  const canEdit = useCan(['sim.manage', 'services.manage'], 'any')
  const providers = useProviders()

  return (
    <>
      <PageHeader title={t('providers.title')} description={t('providers.description')} />
      <CatalogTable
        canEdit={canEdit}
        invalidate={[['services'], ['sim']]}
        deleteConfirm={(name) => t('providers.deleteConfirm', { name })}
        headers={[
          t('providers.name'),
          t('providers.tin'),
          t('providers.phone'),
          t('providers.kind'),
          t('providers.active'),
        ]}
        addLabel={t('providers.add')}
        rows={(providers.data ?? []).map((p) => ({
          id: p.id,
          name: p.name,
          cells: [
            p.name,
            p.tin ?? '—',
            p.phone ?? '—',
            p.is_mobile_operator ? (
              <Badge variant="light">{t('providers.mobile')}</Badge>
            ) : (
              t('providers.provider')
            ),
            p.is_active ? t('common:labels.yes') : t('common:labels.no'),
          ],
          values: {
            name: p.name,
            tin: p.tin,
            phone: p.phone,
            email: p.email,
            address: p.address,
            is_mobile_operator: p.is_mobile_operator,
            is_active: p.is_active,
          },
        }))}
        fields={[
          { key: 'name', label: t('providers.name'), kind: 'text', required: true },
          { key: 'tin', label: t('providers.tin'), kind: 'text' },
          { key: 'phone', label: t('providers.phone'), kind: 'text' },
          { key: 'email', label: t('providers.email'), kind: 'text' },
          { key: 'address', label: t('providers.address'), kind: 'text' },
          { key: 'is_mobile_operator', label: t('providers.isMobile'), kind: 'check' },
          { key: 'is_active', label: t('providers.active'), kind: 'check' },
        ]}
        save={(v, id) => s.saveProvider(v, id)}
        remove={s.deleteProvider}
      />
    </>
  )
}
