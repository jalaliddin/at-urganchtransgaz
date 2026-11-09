import { api } from '@/api/client'
import type { MessageResponse, Paginated, Resource } from '@/api/types'
import type {
  Cartridge,
  ConsumptionReport,
  CounterReading,
  PrinterDetail,
  PrinterModelRef,
  PrinterRow,
  Replacement,
} from './types'

type Params = Record<string, string | number>
type Values = Record<string, unknown>

const data = <T>(p: Promise<{ data: Resource<T> }>) => p.then((r) => r.data.data)
const message = (p: Promise<{ data: MessageResponse }>) => p.then((r) => r.data.message)

export const listPrinters = (params: Params) =>
  api.get<Paginated<PrinterRow>>('/printers', { params }).then((r) => r.data)
export const getPrinter = (id: number) => data<PrinterDetail>(api.get(`/printers/${id}`))
export const savePrinterProfile = (id: number, values: Values) =>
  data<PrinterDetail>(api.put(`/printers/${id}`, values))
export const listCounters = (id: number, params: Params) =>
  api.get<Paginated<CounterReading>>(`/printers/${id}/counters`, { params }).then((r) => r.data)
export const addCounter = (id: number, values: Values) =>
  data<CounterReading>(api.post(`/printers/${id}/counters`, values))
export const deleteCounter = (id: number) => message(api.delete(`/printer-counters/${id}`))
export const listPrinterReplacements = (id: number, params: Params) =>
  api.get<Paginated<Replacement>>(`/printers/${id}/replacements`, { params }).then((r) => r.data)
export const replaceCartridge = (id: number, values: Values) =>
  data<Replacement>(api.post(`/printers/${id}/replacements`, values))
export const listReplacements = (params: Params) =>
  api.get<Paginated<Replacement>>('/cartridge-replacements', { params }).then((r) => r.data)
export const cancelReplacement = (id: number, reason: string) =>
  data<Replacement>(api.post(`/cartridge-replacements/${id}/cancel`, { reason }))
export const listCartridges = (params: Params) =>
  api.get<Paginated<Cartridge>>('/cartridge-models', { params }).then((r) => r.data)
export const saveCartridge = (values: Values, id?: number) =>
  data<Cartridge>(id ? api.put(`/cartridge-models/${id}`, values) : api.post('/cartridge-models', values))
export const deleteCartridge = (id: number) => message(api.delete(`/cartridge-models/${id}`))
export const listPrinterModels = () => data<PrinterModelRef[]>(api.get('/printer-models'))
export const printerReport = (params: Params) =>
  data<ConsumptionReport>(api.get('/printer-report', { params }))
