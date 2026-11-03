import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/PageHeader'
import { DepartmentsTable } from './DepartmentsTable'

export function DepartmentsPage() {
  const { t } = useTranslation('departments')
  return (
    <>
      <PageHeader title={t('title')} description={t('description')} />
      <DepartmentsTable storeKey="departments-table" />
    </>
  )
}
