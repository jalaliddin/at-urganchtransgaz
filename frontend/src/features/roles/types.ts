export interface Role {
  id: number
  name: string
  display_name: string
  description: string | null
  is_system: boolean
  permissions: string[]
  users_count?: number
}

export interface PermissionModule {
  module: string
  permissions: string[]
}
