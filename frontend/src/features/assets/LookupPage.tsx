import { Alert, Button, Center, Loader, Stack } from '@mantine/core'
import { useQuery } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { lookupAsset } from './api'

/** QR target: /assets/lookup/<inventory> opens the card when the asset is in the user's scope. */
export function LookupPage() {
  const { t } = useTranslation('assets')
  const navigate = useNavigate()
  const inventory = useParams().inventory ?? ''
  const query = useQuery({
    queryKey: ['assets', 'lookup', inventory],
    queryFn: () => lookupAsset(inventory),
    retry: false,
  })

  useEffect(() => {
    if (query.data) navigate(`/assets/${query.data.id}`, { replace: true })
  }, [query.data, navigate])

  if (query.isError) {
    return (
      <Stack maw={520}>
        <Alert color="orange" title={inventory}>
          {t('lookup.notFound')}
        </Alert>
        <Button component={Link} to="/assets/mine" variant="default" w="fit-content">
          {t('mine.title')}
        </Button>
      </Stack>
    )
  }

  return (
    <Center py="xl" aria-label={t('lookup.searching')}>
      <Loader />
    </Center>
  )
}
