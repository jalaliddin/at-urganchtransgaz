import { api } from '@/api/client'
import type { MessageResponse, Paginated, Resource } from '@/api/types'
import type {
  Availability,
  BalanceRow,
  JournalRow,
  Material,
  MaterialCategory,
  StockCount,
  StockCountDetail,
  StockDocument,
  StockDocumentDetail,
  StockDocumentInput,
  Unit,
  Warehouse,
} from './types'

type Params = Record<string, string | number>
type Values = Record<string, unknown>

const data = <T>(p: Promise<{ data: Resource<T> }>) => p.then((r) => r.data.data)
const message = (p: Promise<{ data: MessageResponse }>) => p.then((r) => r.data.message)
const save = <T>(path: string, values: Values, id?: number) =>
  data<T>(id ? api.put(`${path}/${id}`, values) : api.post(path, values))

// Catalog
export const listUnits = () => data<Unit[]>(api.get('/units'))
export const saveUnit = (values: Values, id?: number) => save<Unit>('/units', values, id)
export const deleteUnit = (id: number) => message(api.delete(`/units/${id}`))
export const listMaterialCategories = () => data<MaterialCategory[]>(api.get('/material-categories'))
export const saveMaterialCategory = (values: Values, id?: number) =>
  save<MaterialCategory>('/material-categories', values, id)
export const deleteMaterialCategory = (id: number) => message(api.delete(`/material-categories/${id}`))
export const listMaterials = (params: Params) =>
  api.get<Paginated<Material>>('/materials', { params }).then((r) => r.data)
export const saveMaterial = (values: Values, id?: number) => save<Material>('/materials', values, id)
export const deleteMaterial = (id: number) => message(api.delete(`/materials/${id}`))
export const listWarehouses = (params: Params = {}) => data<Warehouse[]>(api.get('/warehouses', { params }))
export const saveWarehouse = (values: Values, id?: number) => save<Warehouse>('/warehouses', values, id)
export const deleteWarehouse = (id: number) => message(api.delete(`/warehouses/${id}`))

// Stock
export const listBalances = (params: Params) =>
  api.get<Paginated<BalanceRow>>('/stock-balances', { params }).then((r) => r.data)
export const getAvailability = (warehouseId: number, materialId: number) =>
  data<Availability>(
    api.get('/stock-availability', { params: { warehouse_id: warehouseId, material_id: materialId } }),
  )
export const listDocuments = (params: Params) =>
  api.get<Paginated<StockDocument>>('/stock-documents', { params }).then((r) => r.data)
export const getDocument = (id: number) => data<StockDocumentDetail>(api.get(`/stock-documents/${id}`))
export const postDocument = (input: StockDocumentInput) =>
  data<StockDocumentDetail>(api.post('/stock-documents', input))
export function attachDocumentFile(id: number, file: File) {
  const form = new FormData()
  form.append('file', file)
  return data<StockDocumentDetail>(api.post(`/stock-documents/${id}/file`, form))
}
export const listMovements = (params: Params) =>
  api.get<Paginated<JournalRow>>('/stock-movements', { params }).then((r) => r.data)

// Counts
export const listCounts = (params: Params) =>
  api.get<Paginated<StockCount>>('/stock-counts', { params }).then((r) => r.data)
export const getCount = (id: number) => data<StockCountDetail>(api.get(`/stock-counts/${id}`))
export const startCount = (warehouseId: number, note?: string) =>
  data<StockCountDetail>(api.post('/stock-counts', { warehouse_id: warehouseId, note }))
export const recordCount = (id: number, lines: { id: number; counted_quantity: string | null }[]) =>
  data<StockCountDetail>(api.patch(`/stock-counts/${id}/lines`, { lines }))
export const addCountLine = (id: number, values: Values) =>
  data<StockCountDetail>(api.post(`/stock-counts/${id}/lines`, values))
export const closeCount = (id: number) => data<StockCountDetail>(api.post(`/stock-counts/${id}/close`))
export const cancelCount = (id: number) => data<StockCountDetail>(api.post(`/stock-counts/${id}/cancel`))
