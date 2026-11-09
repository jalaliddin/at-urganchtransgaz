import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { BoardPage } from './BoardPage'
import { TaskPage } from './TaskPage'
import { TicketTasksPanel } from './TicketTasksPanel'
import { WorkloadPage } from './WorkloadPage'
import type { BoardCard, BoardDetail, TaskDetail } from './types'

const t = (key: string, options?: Record<string, unknown>) => i18n.t(`tasks:${key}`, options)

const board: BoardDetail = {
  id: 1,
  name: 'AT xizmati',
  description: null,
  is_archived: false,
  organization: { id: 6, name: 'Urganch filiali' },
  open_tasks: 1,
  overdue_tasks: 1,
  columns: [
    { id: 10, name: 'Backlog', category: 'open', position: 1, wip_limit: null },
    { id: 11, name: 'Jarayonda', category: 'open', position: 2, wip_limit: 2 },
    { id: 12, name: 'Bajarildi', category: 'done', position: 3, wip_limit: null },
  ],
  labels: [{ id: 3, name: 'Tarmoq', color: 'teal' }],
}

const card: BoardCard = {
  id: 7,
  number: 'T-2026-00007',
  title: 'Switchni almashtirish',
  board_id: 1,
  column_id: 10,
  position: 1,
  priority: 'high',
  project: null,
  organization: { id: 6, name: 'Urganch filiali' },
  assignee: { id: 4, name: 'Aziz Karimov' },
  start_date: null,
  due_date: '2026-11-05',
  completed_at: null,
  is_overdue: true,
  labels: [{ id: 3, name: 'Tarmoq', color: 'teal' }],
  checklist_total: 2,
  checklist_done: 1,
  comments_count: 0,
  can_move: true,
}

const detail: TaskDetail = {
  ...card,
  description: 'Yadro switchi',
  board: { id: 1, name: 'AT xizmati', is_archived: false },
  column: board.columns[0]!,
  creator: { id: 2, name: 'Markaz' },
  ticket: null,
  watchers: [],
  checklist: [{ id: 30, title: 'Kabelni tekshirish', is_done: false, position: 1, done_at: null }],
  comments: [],
  attachments: [],
  history: [
    {
      id: 1,
      event: 'created',
      payload: {},
      user: { id: 2, name: 'Markaz' },
      created_at: '2026-11-09T05:00:00Z',
    },
  ],
  created_at: '2026-11-09T05:00:00Z',
  can: { edit: true, comment: true, delete: false },
}

const boardHandlers = [
  http.get('*/api/v1/task-boards/1', () => HttpResponse.json({ data: board })),
  http.get('*/api/v1/task-boards/1/members', () =>
    HttpResponse.json({ data: [{ id: 4, name: 'Aziz Karimov' }] }),
  ),
  http.get('*/api/v1/task-boards/1/cards', () =>
    HttpResponse.json({
      data: {
        columns: board.columns.map((c) => ({ ...c, cards: c.id === 10 ? [card] : [], hidden_done: 0 })),
      },
    }),
  ),
]

describe('BoardPage', () => {
  it('shows the columns with their cards and moves a card through its menu', async () => {
    let moved: unknown
    server.use(
      ...boardHandlers,
      http.post('*/api/v1/tasks/7/move', async ({ request }) => {
        moved = await request.json()
        return HttpResponse.json({ data: detail })
      }),
    )
    renderWithProviders(<BoardPage />, {
      me: makeMe({ permissions: ['tasks.view', 'tasks.create'] }),
      path: '/tasks/boards/:id',
      route: '/tasks/boards/1',
    })

    const cardEl = await screen.findByRole('button', { name: /T-2026-00007: Switchni almashtirish/ })
    expect(within(cardEl).getByText('1/2')).toBeInTheDocument()
    expect(screen.getByText('0/2')).toBeInTheDocument()
    await userEvent.click(
      within(cardEl).getByRole('button', { name: `${t('board.moveTo')}: Switchni almashtirish` }),
    )
    await userEvent.click(await screen.findByRole('menuitem', { name: 'Jarayonda' }))
    await waitFor(() => expect(moved).toEqual({ column_id: 11, position: 9999 }))
  })
})

describe('TaskPage', () => {
  it('ticks a checklist item and posts a comment', async () => {
    let toggled: unknown
    let commented: unknown
    server.use(
      ...boardHandlers,
      http.get('*/api/v1/tasks/7', () => HttpResponse.json({ data: detail })),
      http.patch('*/api/v1/task-checklist-items/30', async ({ request }) => {
        toggled = await request.json()
        return HttpResponse.json({ data: detail })
      }),
      http.post('*/api/v1/tasks/7/comments', async ({ request }) => {
        commented = await request.json()
        return HttpResponse.json({ data: detail }, { status: 201 })
      }),
    )
    renderWithProviders(<TaskPage />, {
      me: makeMe({ permissions: ['tasks.view', 'tasks.create'] }),
      path: '/tasks/:id',
      route: '/tasks/7',
    })

    await userEvent.click(await screen.findByRole('checkbox', { name: 'Kabelni tekshirish' }))
    await waitFor(() => expect(toggled).toEqual({ is_done: true }))
    await userEvent.type(screen.getByRole('textbox', { name: t('detail.comment') }), 'Switch keldi')
    await userEvent.click(screen.getByRole('button', { name: t('detail.send') }))
    await waitFor(() => expect(commented).toEqual({ body: 'Switch keldi' }))
  })
})

describe('TicketTasksPanel', () => {
  it('creates a task from the ticket on the chosen board', async () => {
    let body: unknown
    server.use(
      http.get('*/api/v1/tickets/5/tasks', () => HttpResponse.json({ data: [] })),
      http.get('*/api/v1/task-boards', () => HttpResponse.json({ data: [board] })),
      http.get('*/api/v1/task-boards/1/members', () =>
        HttpResponse.json({ data: [{ id: 4, name: 'Aziz Karimov' }] }),
      ),
      http.post('*/api/v1/tickets/5/tasks', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: detail }, { status: 201 })
      }),
    )
    renderWithProviders(<TicketTasksPanel ticketId={5} />, {
      me: makeMe({ permissions: ['tasks.view', 'tasks.create'] }),
      routes: [{ path: '/tasks/:id', element: <p>task page</p> }],
    })

    expect(await screen.findByText(t('ticket.empty'))).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: t('ticket.create') }))
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByRole('button', { name: t('ticket.create') }))
    expect(await dialog.findByText(i18n.t('validation:required'))).toBeInTheDocument()
    await userEvent.click(dialog.getByRole('combobox', { name: new RegExp(`^${t('ticket.board')}`) }))
    await userEvent.click(await screen.findByRole('option', { name: 'AT xizmati' }))
    await userEvent.click(dialog.getByRole('button', { name: t('ticket.create') }))
    await waitFor(() => expect(body).toMatchObject({ board_id: 1, assignee_id: null }))
    expect(await screen.findByText('task page')).toBeInTheDocument()
  })
})

describe('WorkloadPage', () => {
  it('lists the load per person, with overdue counts', async () => {
    server.use(
      http.get('*/api/v1/tasks/workload', () =>
        HttpResponse.json({
          data: {
            by_assignee: [
              { user: { id: 4, name: 'Aziz Karimov' }, open: 3, overdue: 1, due_week: 1, done_30: 5 },
              { user: null, open: 1, overdue: 0, due_week: 0, done_30: 0 },
            ],
            by_organization: [
              {
                organization: { id: 6, name: 'Urganch filiali' },
                open: 4,
                overdue: 1,
                due_week: 1,
                done_30: 5,
              },
            ],
          },
        }),
      ),
    )
    renderWithProviders(<WorkloadPage />, {
      me: makeMe({ permissions: ['tasks.view', 'tasks.workload', 'tasks.export'] }),
    })

    expect(await screen.findByText('Aziz Karimov')).toBeInTheDocument()
    expect(screen.getByText(t('workload.unassigned'))).toBeInTheDocument()
    expect(screen.getByText('Urganch filiali')).toBeInTheDocument()
  })
})
