import { http, HttpResponse } from 'msw'
import { page } from '@/test/handlers'
import type { Asset, AssetCategory } from './types'

export const assetCategoriesFixture: AssetCategory[] = [
  {
    id: 2,
    code: 'computer',
    name: 'Kompyuter, monoblok, noutbuk',
    name_ru: null,
    label: 'Kompyuter, monoblok, noutbuk',
    is_networked: true,
    is_active: true,
    sort_order: 20,
  },
  {
    id: 4,
    code: 'printer',
    name: 'Printer, MFP, skaner',
    name_ru: null,
    label: 'Printer, MFP, skaner',
    is_networked: true,
    is_active: true,
    sort_order: 40,
  },
]

export const catalogHandlers = [
  http.get('*/api/v1/asset-categories', () => HttpResponse.json({ data: assetCategoriesFixture })),
  http.get('*/api/v1/manufacturers', () =>
    HttpResponse.json({ data: [{ id: 1, name: 'HP', website: null }] }),
  ),
  http.get('*/api/v1/asset-models', () => HttpResponse.json({ data: [] })),
  http.get('*/api/v1/locations', () => HttpResponse.json({ data: [] })),
  http.get('*/api/v1/departments', () => HttpResponse.json(page([]))),
  http.get('*/api/v1/employees', () => HttpResponse.json(page([]))),
  http.get('*/api/v1/organizations/:id', ({ params }) =>
    HttpResponse.json({
      data: { id: Number(params.id), name: 'Xorazm GTS', short_name: null, is_active: true },
    }),
  ),
]

export function makeAsset(overrides: Partial<Asset> = {}): Asset {
  return {
    id: 1,
    inventory_number: 'AT-000001',
    name: 'HP ProDesk 400 G7',
    status: 'in_use',
    category: { id: 2, code: 'computer', name: 'Kompyuter, monoblok, noutbuk', is_networked: true },
    manufacturer: { id: 1, name: 'HP' },
    model: null,
    organization: { id: 5, name: 'Xorazm GTS' },
    department: null,
    serial_number: 'CZC1234XYZ',
    hostname: 'urg-pc-214',
    ip_address: '10.20.1.14',
    mac_address: 'AA:BB:CC:DD:EE:FF',
    vlan: null,
    subnet: null,
    holder: { id: 9, name: 'Karimov Aziz', status: 'active' },
    responsible: null,
    location: null,
    room: '214',
    purchase_date: '2025-03-01',
    warranty_until: '2028-03-01',
    warranty_state: 'ok',
    cost: '8500000.00',
    supplier: null,
    specs: null,
    notes: null,
    in_transfer: false,
    created_at: null,
    updated_at: null,
    can: { update: true, delete: false, assign: true, transfer: true, dispose: false },
    ...overrides,
  }
}
