import { fireEvent, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { NewTicketPage } from '@/features/helpdesk/NewTicketPage'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { categoriesHandler, makeTicket } from './testData'

const employee = makeMe({
  permissions: ['dashboard.view', 'helpdesk.create'],
  organization: { id: 4, name: 'Urganch filiali' },
})

function render() {
  return renderWithProviders(<NewTicketPage />, {
    me: employee,
    path: '/helpdesk/new',
    routes: [{ path: '/helpdesk/:id', element: <p>ticket page</p> }],
  })
}

function fileInput(): HTMLInputElement {
  const input = document.querySelector<HTMLInputElement>('input[type="file"]')
  if (!input) throw new Error('file input not found')
  return input
}

describe('NewTicketPage', () => {
  it('validates the form before sending', async () => {
    server.use(categoriesHandler)
    render()

    await userEvent.type(await screen.findByRole('textbox', { name: /Muammo sarlavhasi/ }), 'abc')
    await userEvent.click(screen.getByRole('button', { name: i18n.t('helpdesk:new.submit') }))

    expect(await screen.findByText(i18n.t('validation:titleMin'))).toBeInTheDocument()
    expect(screen.getByText(i18n.t('validation:descriptionMin'))).toBeInTheDocument()
    expect(screen.getByText(i18n.t('validation:required'))).toBeInTheDocument()
    expect(screen.queryByRole('textbox', { name: /Tashkilot/ })).not.toBeInTheDocument()
  })

  it('sends fields and files as multipart and opens the new ticket', async () => {
    let sent: FormData | undefined
    server.use(
      categoriesHandler,
      http.post('*/api/v1/helpdesk/tickets', async ({ request }) => {
        sent = await request.formData()
        return HttpResponse.json({ data: makeTicket({ id: 9, number: 'HD-2026-000009' }) }, { status: 201 })
      }),
    )
    const { router } = render()

    await userEvent.click(await screen.findByRole('combobox', { name: /Kategoriya/ }))
    await userEvent.click(await screen.findByRole('option', { name: 'Printer' }))
    await userEvent.click(screen.getByRole('radio', { name: i18n.t('helpdesk:priority.high') }))
    await userEvent.type(screen.getByRole('textbox', { name: /Muammo sarlavhasi/ }), 'Printer ishlamayapti')
    await userEvent.type(
      screen.getByRole('textbox', { name: /Batafsil tavsif/ }),
      "Qog'ozni tortmayapti, xato 0x01.",
    )
    const photo = new File(['png'], 'xato.png', { type: 'image/png' })
    fireEvent.change(fileInput(), { target: { files: [photo] } })
    expect(await screen.findByText('xato.png')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: i18n.t('helpdesk:new.submit') }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/helpdesk/9'))
    expect(sent?.get('title')).toBe('Printer ishlamayapti')
    expect(sent?.get('category_id')).toBe('2')
    expect(sent?.get('priority')).toBe('high')
    expect(sent?.get('organization_id')).toBe('4')
    expect(sent?.getAll('files[]')).toHaveLength(1)
  })

  it('rejects oversized and unsupported files before upload', async () => {
    server.use(categoriesHandler)
    render()
    const big = new File(['x'], 'skan.pdf', { type: 'application/pdf' })
    Object.defineProperty(big, 'size', { value: 11 * 1024 * 1024 })
    const script = new File(['<?php'], 'shell.php', { type: 'text/plain' })

    fireEvent.change(fileInput(), { target: { files: [big, script] } })

    expect(
      await screen.findByText(i18n.t('helpdesk:dropzone.tooLarge', { name: 'skan.pdf', size: 10 })),
    ).toBeInTheDocument()
    expect(screen.getByText(i18n.t('helpdesk:dropzone.badType', { name: 'shell.php' }))).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /skan.pdf/ })).not.toBeInTheDocument()
  })
})
