import { create } from 'zustand'
import { createJSONStorage, persist, type StateStorage } from 'zustand/middleware'

/** localStorage that degrades to memory (private mode, blocked storage). */
const memory = new Map<string, string>()
const safeStorage: StateStorage = {
  getItem: (key) => {
    try {
      return window.localStorage.getItem(key)
    } catch {
      return memory.get(key) ?? null
    }
  },
  setItem: (key, value) => {
    try {
      window.localStorage.setItem(key, value)
    } catch {
      memory.set(key, value)
    }
  },
  removeItem: (key) => {
    try {
      window.localStorage.removeItem(key)
    } catch {
      memory.delete(key)
    }
  },
}

export interface OrgContext {
  /** The user who chose it: another account signing in on this device ignores it. */
  userId: number
  organizationId: number
}

interface UiState {
  sidebarCollapsed: boolean
  toggleSidebar: () => void
  /** Organization chosen in the header; lists and the dashboard default to its subtree. */
  orgContext: OrgContext | null
  setOrgContext: (context: OrgContext | null) => void
}

export const useUiStore = create<UiState>()(
  persist(
    (set) => ({
      sidebarCollapsed: false,
      toggleSidebar: () => set((s) => ({ sidebarCollapsed: !s.sidebarCollapsed })),
      orgContext: null,
      setOrgContext: (orgContext) => set({ orgContext }),
    }),
    {
      name: 'at.ui',
      version: 2,
      // v1 stored a bare organization id that was not tied to a user: drop it.
      migrate: (persisted) => ({
        sidebarCollapsed: Boolean((persisted as { sidebarCollapsed?: boolean } | null)?.sidebarCollapsed),
        orgContext: null,
      }),
      storage: createJSONStorage(() => safeStorage),
    },
  ),
)
