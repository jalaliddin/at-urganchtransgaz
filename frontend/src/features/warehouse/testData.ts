import { http, HttpResponse } from 'msw'
import { page } from '@/test/handlers'
import type { BalanceRow, Material, StockCountDetail, StockDocumentDetail, Warehouse } from './types'

export const warehouseFixture: Warehouse = {
  id: 3,
  name: 'Markaziy ombor',
  code: null,
  description: null,
  is_active: true,
  organization: { id: 5, name: 'Xorazm GTS' },
  location: null,
  responsible: null,
}

export const materialFixture: Material = {
  id: 11,
  code: 'CE285A',
  name: 'HP 85A kartrij',
  category: { id: 1, name: 'Printer kartrijlari va tonerlar' },
  unit: { id: 1, name: 'dona' },
  track_batches: false,
  track_expiry: false,
  min_quantity: '5.000',
  description: null,
  is_active: true,
}

export function makeBalance(overrides: Partial<BalanceRow> = {}): BalanceRow {
  return {
    id: '3:11',
    warehouse: { id: 3, name: 'Markaziy ombor', organization: { id: 5, name: 'Xorazm GTS' } },
    material: {
      id: 11,
      code: 'CE285A',
      name: 'HP 85A kartrij',
      unit: 'dona',
      track_batches: false,
      track_expiry: false,
      category: 'Kartrijlar',
    },
    quantity: '3.000',
    value: '450000.00',
    min_quantity: '5.000',
    is_low: true,
    nearest_expiry: null,
    lots: [],
    ...overrides,
  }
}

export function makeDocument(overrides: Partial<StockDocumentDetail> = {}): StockDocumentDetail {
  return {
    id: 21,
    number: 'IS-2026-00001',
    type: 'issue',
    document_date: '2026-11-08',
    organization: { id: 5, name: 'Xorazm GTS' },
    warehouse: { id: 3, name: 'Markaziy ombor' },
    target_warehouse: null,
    supplier: null,
    supplier_document: null,
    recipient_organization: { id: 5, name: 'Xorazm GTS' },
    recipient_employee: null,
    asset: null,
    reason: 'Printer uchun',
    related_document: null,
    requisition: null,
    stock_count_id: null,
    file: null,
    total_amount: '150000.00',
    created_by: { id: 1, name: 'Admin' },
    created_at: '2026-11-08T05:00:00Z',
    lines: [],
    movements: [],
    returns: [],
    can: { attach_file: true, return: true },
    ...overrides,
  }
}

export function makeCount(overrides: Partial<StockCountDetail> = {}): StockCountDetail {
  return {
    id: 7,
    number: 'SC-2026-00001',
    status: 'open',
    note: null,
    warehouse: { id: 3, name: 'Markaziy ombor' },
    started_by: { id: 1, name: 'Admin' },
    started_at: '2026-11-08T05:00:00Z',
    closed_at: null,
    closed_by: null,
    adjustment_document: null,
    progress: { total: 2, counted: 0 },
    lines: [
      {
        id: 101,
        material: {
          id: 11,
          code: 'CE285A',
          name: 'HP 85A kartrij',
          unit: 'dona',
          track_batches: false,
          track_expiry: false,
        },
        batch: null,
        expires_at: null,
        expected_quantity: '5.000',
        counted_quantity: null,
        difference: null,
      },
      {
        id: 102,
        material: {
          id: 12,
          code: 'A4',
          name: "A4 qog'oz",
          unit: 'pachka',
          track_batches: false,
          track_expiry: false,
        },
        batch: null,
        expires_at: null,
        expected_quantity: '10.000',
        counted_quantity: null,
        difference: null,
      },
    ],
    can: { manage: true },
    ...overrides,
  }
}

export const warehouseCatalogHandlers = [
  http.get('*/api/v1/warehouses', () => HttpResponse.json({ data: [warehouseFixture] })),
  http.get('*/api/v1/materials', () => HttpResponse.json(page([materialFixture]))),
  http.get('*/api/v1/material-categories', () =>
    HttpResponse.json({
      data: [
        {
          id: 1,
          code: 'cartridge',
          name: 'Kartrijlar',
          name_ru: null,
          label: 'Kartrijlar',
          is_active: true,
          sort_order: 10,
        },
      ],
    }),
  ),
  http.get('*/api/v1/units', () =>
    HttpResponse.json({
      data: [
        { id: 1, code: 'pcs', name: 'dona', name_ru: 'шт.', label: 'dona', is_active: true, sort_order: 10 },
      ],
    }),
  ),
]
