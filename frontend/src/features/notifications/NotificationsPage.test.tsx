import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { NotificationsPage } from '@/features/notifications/NotificationsPage'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { page } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'

describe('NotificationsPage', () => {
  it('marks everything as read and refreshes the list', async () => {
    let read = false
    let markAllCalls = 0
    server.use(
      http.get('*/api/v1/notifications', () =>
        HttpResponse.json(
          page([
            {
              id: '0b6f2b8e-5d61-4c3a-9e5e-8a1c0f0f0001',
              type: 'access_changed',
              title_key: 'notifications.access_changed',
              params: { actor: 'Karimov Aziz' },
              url: '/profile',
              read_at: read ? '2026-11-03T08:00:00Z' : null,
              created_at: '2026-11-03T07:00:00Z',
            },
          ]),
        ),
      ),
      http.get('*/api/v1/notifications/unread-count', () =>
        HttpResponse.json({ data: { count: read ? 0 : 1 } }),
      ),
      http.post('*/api/v1/notifications/read-all', () => {
        markAllCalls++
        read = true
        return HttpResponse.json({ message: 'ok' })
      }),
    )
    renderWithProviders(<NotificationsPage />, { me: makeMe() })

    expect(
      await screen.findByText(i18n.t('common:notifications.access_changed', { actor: 'Karimov Aziz' })),
    ).toBeInTheDocument()
    expect(screen.getByText(i18n.t('notifications:unread'))).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: i18n.t('nav:notifications.markAll') }))

    await waitFor(() => expect(markAllCalls).toBe(1))
    await waitFor(() => expect(screen.queryByText(i18n.t('notifications:unread'))).not.toBeInTheDocument())
  })
})
