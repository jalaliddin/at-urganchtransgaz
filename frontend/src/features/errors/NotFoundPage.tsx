import { Button, Center } from '@mantine/core'
import { IconMapOff } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { EmptyState } from '@/components/EmptyState'

export function NotFoundPage() {
  const { t } = useTranslation()
  return (
    <Center mih="60vh">
      <EmptyState
        icon={<IconMapOff size={24} stroke={1.5} />}
        title={t('errors.notFoundTitle')}
        description={t('errors.notFound')}
        action={
          <Button component={Link} to="/" variant="default" mt="sm">
            {t('errors.toHome')}
          </Button>
        }
      />
    </Center>
  )
}
