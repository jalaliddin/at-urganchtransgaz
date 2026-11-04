import type { Me } from '@/features/auth/types'

export function makeMe(overrides: Partial<Me> = {}): Me {
  return {
    id: 1,
    username: 'a.karimov',
    name: 'Karimov Aziz',
    email: 'a.karimov@urtg.uz',
    locale: 'uz',
    must_change_password: false,
    password_expired: false,
    is_super_admin: false,
    organization: { id: 5, name: 'Xorazm GTS' },
    employee: null,
    roles: [{ name: 'org-it-responsible', display_name: "Quyi tashkilot AT mas'uli" }],
    permissions: ['dashboard.view', 'organizations.view', 'employees.view'],
    access: [{ organization_id: 5, organization_name: 'Xorazm GTS', include_descendants: false }],
    notification_preferences: { mail_muted: [] },
    ...overrides,
  }
}
