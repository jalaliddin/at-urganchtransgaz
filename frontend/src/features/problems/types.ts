interface Ref {
  id: number
  name: string
}

export const PROBLEM_STATUSES = ['new', 'investigating', 'known_error', 'resolved', 'closed'] as const
export type ProblemStatus = (typeof PROBLEM_STATUSES)[number]
export const PROBLEM_PRIORITIES = ['low', 'normal', 'high', 'critical'] as const
export type ProblemPriority = (typeof PROBLEM_PRIORITIES)[number]

export interface ProblemRow {
  id: number
  number: string
  title: string
  organization: Ref
  category: { id: number; code: string | null; name: string } | null
  priority: ProblemPriority
  status: ProblemStatus
  assignee: Ref | null
  tickets_count: number
  created_at: string
  resolved_at: string | null
}

export interface HistoryEntry {
  id: number
  event: string
  payload: Record<string, unknown> | null
  comment: string | null
  user: Ref | null
  created_at: string
}

export interface Problem extends ProblemRow {
  description: string | null
  root_cause: string | null
  workaround: string | null
  resolution: string | null
  close_reason: string | null
  closed_at: string | null
  creator: Ref | null
  article: { id: number; number: string; title: string } | null
  tickets: {
    id: number
    number: string
    title: string
    status: string
    organization: Ref | null
    created_at: string
  }[]
  changes: { id: number; number: string; title: string; status: string; planned_start: string | null }[]
  history: HistoryEntry[]
  can: { manage: boolean; transitions: ProblemStatus[] }
}

export interface Candidate {
  category: { id: number; code: string | null; name: string } | null
  organization: Ref | null
  count: number
  ticket_ids: number[]
  last_at: string
}
