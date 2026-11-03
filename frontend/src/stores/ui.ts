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

interface UiState {
  sidebarCollapsed: boolean
  toggleSidebar: () => void
  /** Organization chosen in the header; lists and the dashboard default to its subtree. */
  orgContextId: number | null
  setOrgContext: (id: number | null) => void
}

export const useUiStore = create<UiState>()(
  persist(
    (set) => ({
      sidebarCollapsed: false,
      toggleSidebar: () => set((s) => ({ sidebarCollapsed: !s.sidebarCollapsed })),
      orgContextId: null,
      setOrgContext: (id) => set({ orgContextId: id }),
    }),
    { name: 'at.ui', storage: createJSONStorage(() => safeStorage) },
  ),
)
