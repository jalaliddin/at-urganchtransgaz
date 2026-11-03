import { http, HttpResponse } from 'msw'

/** Paginated envelope helper for list endpoints in tests. */
export function page<T>(data: T[]) {
  return {
    data,
    meta: {
      current_page: 1,
      per_page: 25,
      total: data.length,
      last_page: 1,
      from: data.length ? 1 : null,
      to: data.length || null,
    },
  }
}

export const organizationsFixture = [
  {
    id: 1,
    name: 'Xorazm GTS',
    code: 'GTS',
    parent_id: null,
    type: 'subsidiary',
    is_active: true,
    can: { update: true, delete: true, manage_it_staff: true },
  },
  {
    id: 2,
    name: 'Urganch MGQB',
    code: 'MGQB',
    parent_id: null,
    type: 'subsidiary',
    is_active: true,
    can: { update: true, delete: true, manage_it_staff: true },
  },
]

export const organizationOptionsHandler = http.get('*/api/v1/organizations', () =>
  HttpResponse.json(page(organizationsFixture)),
)
