import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'

/** Mock API used only by tests. Individual tests add handlers with server.use(). */
export const server = setupServer(
  http.get('*/sanctum/csrf-cookie', () => new HttpResponse(null, { status: 204 })),
  http.get('*/api/v1/settings/public', () =>
    HttpResponse.json({
      data: {
        system_name: 'AT.urtg.uz',
        organization_name: '"Urganchtransgaz" MCHJ',
        support_phone: '+998 62 224-00-00',
        support_email: null,
      },
    }),
  ),
)
