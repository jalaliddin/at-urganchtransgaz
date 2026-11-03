import { Button, Center } from '@mantine/core'
import { IconLock } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { EmptyState } from '@/components/EmptyState'

export function ForbiddenPage() {
  const { t } = useTranslation()
  return (
    <Center mih="60vh">
      <EmptyState
        icon={<IconLock size={24} stroke={1.5} />}
        title={t('errors.forbiddenTitle')}
        description={t('errors.forbidden')}
        action={
          <Button component={Link} to="/" variant="default" mt="sm">
            {t('errors.toHome')}
          </Button>
        }
      />
    </Center>
  )
}
