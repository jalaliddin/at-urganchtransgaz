import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/PageHeader'
import { EmployeesTable } from './EmployeesTable'

export function EmployeesPage() {
  const { t } = useTranslation('employees')
  return (
    <>
      <PageHeader title={t('title')} description={t('description')} />
      <EmployeesTable storeKey="employees-table" />
    </>
  )
}
