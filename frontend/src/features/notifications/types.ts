export interface AppNotification {
  id: string
  type: string | null
  title_key: string | null
  params: Record<string, string>
  url: string | null
  read_at: string | null
  created_at: string | null
}
