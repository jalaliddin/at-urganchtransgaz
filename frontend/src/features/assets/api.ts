import { api } from '@/api/client'
import type { MessageResponse, Paginated, Resource } from '@/api/types'
import type {
  Asset,
  AssetAssignment,
  AssetCategory,
  AssetEvent,
  AssetFile,
  AssetModel,
  AssetRelation,
  AssetStats,
  AssetStatus,
  Disposal,
  InventoryItem,
  InventorySession,
  Location,
  Maintenance,
  Manufacturer,
  RelationType,
  ScanResult,
  Transfer,
} from './types'

type Params = Record<string, string | number>

export interface AssetInput {
  organization_id?: number
  inventory_number?: string | null
  name: string
  category_id: number
  model_id?: number | null
  manufacturer_id?: number | null
  department_id?: number | null
  location_id?: number | null
  responsible_employee_id?: number | null
  serial_number?: string | null
  hostname?: string | null
  ip_address?: string | null
  mac_address?: string | null
  vlan?: string | null
  subnet?: string | null
  room?: string | null
  purchase_date?: string | null
  warranty_until?: string | null
  cost?: string | null
  supplier?: string | null
  specs?: string | null
  notes?: string | null
  status?: AssetStatus
}

const data = <T>(p: Promise<{ data: Resource<T> }>) => p.then((r) => r.data.data)

// Assets
export const listAssets = (params: Params) =>
  api.get<Paginated<Asset>>('/assets', { params }).then((r) => r.data)
export const getAsset = (id: number) => data<Asset>(api.get(`/assets/${id}`))
export const lookupAsset = (inventory: string) =>
  data<Asset>(api.get(`/assets/lookup/${encodeURIComponent(inventory)}`))
export const myAssets = () => data<Asset[]>(api.get('/assets/mine'))
export const employeeAssets = (employeeId: number) =>
  data<Asset[]>(api.get(`/employees/${employeeId}/assets`))
export const assetStats = (params: Params) => data<AssetStats>(api.get('/assets/stats', { params }))
export const createAsset = (input: AssetInput) => data<Asset>(api.post('/assets', input))
export const updateAsset = (id: number, input: Partial<AssetInput>) =>
  data<Asset>(api.patch(`/assets/${id}`, input))
export const deleteAsset = (id: number) =>
  api.delete<MessageResponse>(`/assets/${id}`).then((r) => r.data.message)
export const setAssetStatus = (id: number, status: AssetStatus, note?: string) =>
  data<Asset>(api.post(`/assets/${id}/status`, { status, note }))
export const assignAsset = (id: number, employeeId: number, note?: string) =>
  data<Asset>(api.post(`/assets/${id}/assign`, { employee_id: employeeId, note }))
export const returnAsset = (id: number, note?: string) =>
  data<Asset>(api.post(`/assets/${id}/return`, { note }))
export const assetEvents = (id: number) => data<AssetEvent[]>(api.get(`/assets/${id}/events`))
export const assetAssignments = (id: number) => data<AssetAssignment[]>(api.get(`/assets/${id}/assignments`))
export const assetFiles = (id: number) => data<AssetFile[]>(api.get(`/assets/${id}/attachments`))
export function uploadAssetFiles(id: number, files: File[]) {
  const form = new FormData()
  files.forEach((f) => form.append('files[]', f))
  return data<AssetFile[]>(api.post(`/assets/${id}/attachments`, form))
}
export const deleteAssetFile = (id: number, fileId: number) =>
  api.delete(`/assets/${id}/attachments/${fileId}`)
export const assetRelations = (id: number) => data<AssetRelation[]>(api.get(`/assets/${id}/relations`))
export const addRelation = (id: number, relatedId: number, type: RelationType, note?: string) =>
  api.post(`/assets/${id}/relations`, { related_asset_id: relatedId, type, note })
export const deleteRelation = (id: number, relationId: number) =>
  api.delete(`/assets/${id}/relations/${relationId}`)
export const assetMaintenances = (id: number) => data<Maintenance[]>(api.get(`/assets/${id}/maintenances`))
export const openMaintenance = (
  id: number,
  input: { description: string; vendor?: string; ticket_id?: number | null },
) => data<Maintenance>(api.post(`/assets/${id}/maintenances`, input))
export const closeMaintenance = (
  id: number,
  maintenanceId: number,
  input: { result: string; cost?: number | null },
) => data<Maintenance>(api.post(`/assets/${id}/maintenances/${maintenanceId}/close`, input))
export const requestDisposal = (id: number, input: { reason: string; act_number?: string }) =>
  data<Disposal>(api.post(`/assets/${id}/disposals`, input))
export const assetDocuments = (id: number) =>
  data<{ id: number; type: string; number: string | null; title: string }[]>(
    api.get(`/assets/${id}/documents`),
  )

// Disposals
export const listDisposals = (params: Params) =>
  api.get<Paginated<Disposal>>('/asset-disposals', { params }).then((r) => r.data)
export const decideDisposal = (id: number, approve: boolean, note?: string) =>
  data<Disposal>(api.post(`/asset-disposals/${id}/${approve ? 'approve' : 'reject'}`, { note }))

// Transfers
export const listTransfers = (params: Params) =>
  api.get<Paginated<Transfer>>('/asset-transfers', { params }).then((r) => r.data)
export const getTransfer = (id: number) => data<Transfer>(api.get(`/asset-transfers/${id}`))
export const createTransfer = (input: {
  from_organization_id: number
  to_organization_id: number
  note?: string
  asset_ids?: number[]
}) => data<Transfer>(api.post('/asset-transfers', input))
export const addTransferItems = (id: number, assetIds: number[]) =>
  data<Transfer>(api.post(`/asset-transfers/${id}/items`, { asset_ids: assetIds }))
export const removeTransferItem = (id: number, assetId: number) =>
  data<Transfer>(api.delete(`/asset-transfers/${id}/items/${assetId}`))
export const transferAction = (id: number, action: 'send' | 'receive' | 'cancel', note?: string) =>
  data<Transfer>(api.post(`/asset-transfers/${id}/${action}`, { note }))

// Inventory
export const listSessions = (params: Params) =>
  api.get<Paginated<InventorySession>>('/inventory-sessions', { params }).then((r) => r.data)
export const getSession = (id: number) => data<InventorySession>(api.get(`/inventory-sessions/${id}`))
export const startSession = (input: {
  organization_id: number
  name: string
  include_descendants: boolean
  note?: string
}) => data<InventorySession>(api.post('/inventory-sessions', input))
export const sessionItems = (id: number, params: Params) =>
  api.get<Paginated<InventoryItem>>(`/inventory-sessions/${id}/items`, { params }).then((r) => r.data)
export const scanCode = (id: number, code: string) =>
  data<{ result: ScanResult; item: InventoryItem; session: InventorySession }>(
    api.post(`/inventory-sessions/${id}/scan`, { code }),
  )
export const closeSession = (id: number) =>
  data<InventorySession>(api.post(`/inventory-sessions/${id}/close`))

// Catalog
export const listCategories = () => data<AssetCategory[]>(api.get('/asset-categories'))
export const saveCategory = (input: Partial<AssetCategory>, id?: number) =>
  data<AssetCategory>(id ? api.put(`/asset-categories/${id}`, input) : api.post('/asset-categories', input))
export const deleteCategory = (id: number) => api.delete(`/asset-categories/${id}`)
export const listManufacturers = () => data<Manufacturer[]>(api.get('/manufacturers'))
export const saveManufacturer = (input: { name: string; website?: string | null }, id?: number) =>
  data<Manufacturer>(id ? api.put(`/manufacturers/${id}`, input) : api.post('/manufacturers', input))
export const deleteManufacturer = (id: number) => api.delete(`/manufacturers/${id}`)
export const listModels = (params: Params = {}) => data<AssetModel[]>(api.get('/asset-models', { params }))
export const saveModel = (
  input: { manufacturer_id: number; category_id: number; name: string; specs?: string | null },
  id?: number,
) => data<AssetModel>(id ? api.put(`/asset-models/${id}`, input) : api.post('/asset-models', input))
export const deleteModel = (id: number) => api.delete(`/asset-models/${id}`)
export const listLocations = (params: Params = {}) => data<Location[]>(api.get('/locations', { params }))
export const saveLocation = (
  input: { organization_id?: number; name: string; parent_id?: number | null; code?: string | null },
  id?: number,
) => data<Location>(id ? api.put(`/locations/${id}`, input) : api.post('/locations', input))
export const deleteLocation = (id: number) => api.delete(`/locations/${id}`)
