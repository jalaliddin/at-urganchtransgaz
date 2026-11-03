import { zodResolver } from '@hookform/resolvers/zod'
import { Alert, Anchor, Button, PasswordInput, Stack, Text } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { applyApiErrors, fieldError } from '@/lib/forms'
import { AuthLayout } from '@/layouts/AuthLayout'
import { useChangePassword, useLogout, useMe } from './queries'
import { changePasswordSchema, type ChangePasswordValues } from './schemas'

export function ChangePasswordPage() {
  const { t } = useTranslation('auth')
  const navigate = useNavigate()
  const { data: me } = useMe()
  const change = useChangePassword()
  const logout = useLogout()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<ChangePasswordValues>({
    resolver: zodResolver(changePasswordSchema),
    defaultValues: { current_password: '', password: '', password_confirmation: '' },
  })

  const onSubmit = handleSubmit(async (values) => {
    try {
      const message = await change.mutateAsync(values)
      notifications.show({ color: 'teal', message })
      navigate('/', { replace: true })
    } catch (error) {
      applyApiErrors(error, setError)
    }
  })

  const reason = me?.must_change_password
    ? t('change.forced')
    : me?.password_expired
      ? t('change.expired')
      : undefined

  return (
    <AuthLayout title={t('change.title')} description={reason}>
      <form onSubmit={onSubmit} noValidate>
        <Stack gap="md">
          {change.error && !Object.keys(errors).length && (
            <Alert color="red" variant="light">
              {change.error.message}
            </Alert>
          )}
          <PasswordInput
            label={t('change.current')}
            autoComplete="current-password"
            error={fieldError(t, errors.current_password)}
            {...register('current_password')}
          />
          <PasswordInput
            label={t('change.new')}
            autoComplete="new-password"
            error={fieldError(t, errors.password)}
            {...register('password')}
          />
          <PasswordInput
            label={t('change.confirm')}
            autoComplete="new-password"
            error={fieldError(t, errors.password_confirmation)}
            {...register('password_confirmation')}
          />
          <Text size="sm" c="dimmed">
            {t('change.hint')}
          </Text>
          <Button type="submit" loading={change.isPending} fullWidth>
            {t('change.submit')}
          </Button>
        </Stack>
      </form>
      <Anchor component="button" type="button" size="sm" ta="left" onClick={() => logout.mutate()}>
        {t('change.otherAccount')}
      </Anchor>
    </AuthLayout>
  )
}
