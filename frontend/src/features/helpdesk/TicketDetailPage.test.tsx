import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { TicketDetailPage } from '@/features/helpdesk/TicketDetailPage'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { makeTicket } from './testData'
import type { Ticket } from './types'

function serve(ticket: Ticket, onPost?: (path: string, body: unknown) => void) {
  server.use(
    http.get('*/api/v1/helpdesk/tickets/1', () => HttpResponse.json({ data: ticket })),
    http.get('*/api/v1/helpdesk/tickets/1/comments', () =>
      HttpResponse.json({
        data: [
          {
            id: 1,
            body: 'Qaysi xonada?',
            is_internal: false,
            user: { id: 8, name: 'Rahimov Bek' },
            attachments: [],
            created_at: '2026-11-02T06:00:00Z',
          },
        ],
      }),
    ),
    http.get('*/api/v1/helpdesk/tickets/1/attachments', () => HttpResponse.json({ data: [] })),
    http.get('*/api/v1/helpdesk/tickets/1/links', () => HttpResponse.json({ data: [] })),
    http.get('*/api/v1/helpdesk/tickets/1/documents', () => HttpResponse.json({ data: [] })),
    http.post('*/api/v1/helpdesk/tickets/1/:action', async ({ request, params }) => {
      onPost?.(String(params.action), await request.json())
      return HttpResponse.json(
        { data: { ...ticket, id: 1 } },
        { status: params.action === 'comments' ? 201 : 200 },
      )
    }),
  )
}

const handlerMe = makeMe({ permissions: ['helpdesk.view', 'helpdesk.process', 'helpdesk.assign'] })
const requesterMe = makeMe({ permissions: ['helpdesk.create'] })

function render(me = handlerMe) {
  return renderWithProviders(<TicketDetailPage />, { me, path: '/helpdesk/:id', route: '/helpdesk/1' })
}

describe('TicketDetailPage', () => {
  it('gives the requester a plain conversation without handler tools', async () => {
    serve(makeTicket())
    render(requesterMe)

    expect(await screen.findByText('Qaysi xonada?')).toBeInTheDocument()
    expect(
      screen.getByRole('textbox', { name: i18n.t('helpdesk:detail.commentPlaceholder') }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('switch', { name: new RegExp(i18n.t('helpdesk:detail.internal')) }),
    ).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: i18n.t('helpdesk:workflow.assign') })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: i18n.t('helpdesk:workflow.rate') })).not.toBeInTheDocument()
  })

  it('lets the requester rate a resolved ticket', async () => {
    const posts: [string, unknown][] = []
    serve(
      makeTicket({
        status: 'resolved',
        resolved_at: '2099-11-02T08:00:00Z',
        allowed_actions: ['comment', 'attach', 'rate'],
        allowed_transitions: ['closed', 'in_progress'],
      }),
      (path, body) => posts.push([path, body]),
    )
    render(requesterMe)

    expect(
      await screen.findByRole('button', { name: i18n.t('helpdesk:workflow.to.reopen') }),
    ).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: i18n.t('helpdesk:workflow.rate') }))
    const star = await screen.findByRole('radio', { name: '4' })
    // Keyboard selection: Mantine Rating's pointer handling needs layout jsdom does not have.
    star.focus()
    await userEvent.keyboard('{Enter}')
    expect(screen.getByRole('button', { name: i18n.t('helpdesk:workflow.confirm') })).toBeEnabled()
    await userEvent.click(screen.getByRole('button', { name: i18n.t('helpdesk:workflow.confirm') }))

    await waitFor(() => expect(posts).toContainEqual(['rate', { rating: 4 }]))
  })

  it('requires a resolution note before resolving', async () => {
    const posts: [string, unknown][] = []
    serve(
      makeTicket({
        status: 'in_progress',
        is_requester: false,
        allowed_actions: ['update', 'comment', 'comment_internal', 'assign', 'log_time', 'link', 'attach'],
        allowed_transitions: ['waiting_user', 'resolved'],
      }),
      (path, body) => posts.push([path, body]),
    )
    render()

    await userEvent.click(
      await screen.findByRole('button', { name: i18n.t('helpdesk:workflow.to.resolved') }),
    )
    await userEvent.click(await screen.findByRole('button', { name: i18n.t('helpdesk:workflow.confirm') }))
    expect(await screen.findByText(i18n.t('validation:required'))).toBeInTheDocument()
    expect(posts).toHaveLength(0)

    await userEvent.type(
      screen.getByRole('textbox', { name: new RegExp(i18n.t('helpdesk:workflow.resolutionLabel')) }),
      'Kartrij almashtirildi',
    )
    await userEvent.click(screen.getByRole('button', { name: i18n.t('helpdesk:workflow.confirm') }))
    await waitFor(() =>
      expect(posts).toContainEqual(['transition', { status: 'resolved', note: 'Kartrij almashtirildi' }]),
    )
  })

  it('sends internal notes from handlers', async () => {
    const posts: [string, unknown][] = []
    serve(
      makeTicket({
        status: 'in_progress',
        is_requester: false,
        allowed_actions: ['comment', 'comment_internal', 'attach'],
        allowed_transitions: [],
      }),
      (path, body) => posts.push([path, body]),
    )
    render()

    await userEvent.type(
      await screen.findByRole('textbox', { name: i18n.t('helpdesk:detail.commentPlaceholder') }),
      'Server logini tekshirdim',
    )
    await userEvent.click(
      screen.getByRole('switch', { name: new RegExp(i18n.t('helpdesk:detail.internal')) }),
    )
    await userEvent.click(screen.getByRole('button', { name: i18n.t('helpdesk:detail.send') }))

    await waitFor(() =>
      expect(posts).toContainEqual(['comments', { body: 'Server logini tekshirdim', is_internal: true }]),
    )
  })
})
