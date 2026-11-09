import { api } from '@/api/client'
import type { MessageResponse, Paginated, Resource } from '@/api/types'
import type {
  Board,
  BoardColumn,
  BoardDetail,
  Card,
  Column,
  Label,
  Recurrence,
  Ref,
  TaskDetail,
  Workload,
} from './types'

type Params = Record<string, string | number>
type Values = Record<string, unknown>

const data = <T>(p: Promise<{ data: Resource<T> }>) => p.then((r) => r.data.data)
const message = (p: Promise<{ data: MessageResponse }>) => p.then((r) => r.data.message)

export const listBoards = (params: Params = {}) => data<Board[]>(api.get('/task-boards', { params }))
export const getBoard = (id: number) => data<BoardDetail>(api.get(`/task-boards/${id}`))
export const saveBoard = (values: Values, id?: number) =>
  data<BoardDetail>(id ? api.put(`/task-boards/${id}`, values) : api.post('/task-boards', values))
export const deleteBoard = (id: number) => message(api.delete(`/task-boards/${id}`))
export const boardMembers = (id: number) => data<Ref[]>(api.get(`/task-boards/${id}/members`))
export const boardCards = (id: number, params: Params) =>
  data<{ columns: BoardColumn[] }>(api.get(`/task-boards/${id}/cards`, { params }))

export const addColumn = (boardId: number, values: Values) =>
  data<Column>(api.post(`/task-boards/${boardId}/columns`, values))
export const updateColumn = (id: number, values: Values) =>
  data<Column>(api.put(`/task-columns/${id}`, values))
export const deleteColumn = (id: number) => message(api.delete(`/task-columns/${id}`))
export const orderColumns = (boardId: number, ids: number[]) =>
  data<BoardDetail>(api.post(`/task-boards/${boardId}/columns/order`, { ids }))
export const saveLabel = (boardId: number, values: Values, id?: number) =>
  data<Label>(id ? api.put(`/task-labels/${id}`, values) : api.post(`/task-boards/${boardId}/labels`, values))
export const deleteLabel = (id: number) => message(api.delete(`/task-labels/${id}`))

export const listTasks = (params: Params) =>
  api.get<Paginated<Card>>('/tasks', { params }).then((r) => r.data)
export const getTask = (id: number) => data<TaskDetail>(api.get(`/tasks/${id}`))
export const createTask = (boardId: number, values: Values) =>
  data<TaskDetail>(api.post(`/task-boards/${boardId}/tasks`, values))
export const updateTask = (id: number, values: Values) => data<TaskDetail>(api.put(`/tasks/${id}`, values))
export const deleteTask = (id: number) => message(api.delete(`/tasks/${id}`))
export const moveTask = (id: number, columnId: number, position: number) =>
  data<TaskDetail>(api.post(`/tasks/${id}/move`, { column_id: columnId, position }))
export const addChecklistItem = (id: number, title: string) =>
  data<TaskDetail>(api.post(`/tasks/${id}/checklist`, { title }))
export const updateChecklistItem = (itemId: number, values: Values) =>
  data<TaskDetail>(api.patch(`/task-checklist-items/${itemId}`, values))
export const deleteChecklistItem = (itemId: number) =>
  data<TaskDetail>(api.delete(`/task-checklist-items/${itemId}`))
export const addComment = (id: number, body: string) =>
  data<TaskDetail>(api.post(`/tasks/${id}/comments`, { body }))
export const addAttachments = (id: number, files: File[]) => {
  const body = new FormData()
  files.forEach((f) => body.append('files[]', f))
  return data<TaskDetail>(api.post(`/tasks/${id}/attachments`, body))
}
export const ticketTasks = (ticketId: number) => data<Card[]>(api.get(`/tickets/${ticketId}/tasks`))
export const taskFromTicket = (ticketId: number, values: Values) =>
  data<TaskDetail>(api.post(`/tickets/${ticketId}/tasks`, values))

export const listRecurrences = (boardId: number) =>
  data<Recurrence[]>(api.get(`/task-boards/${boardId}/recurrences`))
export const saveRecurrence = (boardId: number, values: Values, id?: number) =>
  data<Recurrence>(
    id ? api.put(`/task-recurrences/${id}`, values) : api.post(`/task-boards/${boardId}/recurrences`, values),
  )
export const deleteRecurrence = (id: number) => message(api.delete(`/task-recurrences/${id}`))

export const workload = (params: Params) => data<Workload>(api.get('/tasks/workload', { params }))
