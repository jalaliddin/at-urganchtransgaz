import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterAll, afterEach, beforeAll } from 'vitest'
import '@/i18n'
import { server } from './server'

// jsdom lacks these browser APIs that Mantine relies on.
// matchMedia evaluates width queries against the jsdom viewport (1024px); other features (dark mode,
// reduced motion) are false, like a default desktop browser.
function evaluateMedia(query: string): boolean {
  // Browsers treat an empty query (and "all") as always matching.
  if (query.trim() === '' || query.trim() === 'all') return true
  const px = (value: string, unit: string) => Number(value) * (unit === 'em' || unit === 'rem' ? 16 : 1)
  const conditions = [...query.matchAll(/\((min|max)-width:\s*([\d.]+)(px|em|rem)\)/g)]
  if (conditions.length === 0) return false
  return conditions.every(([, kind, value = '0', unit = 'px']) =>
    kind === 'min' ? window.innerWidth >= px(value, unit) : window.innerWidth <= px(value, unit),
  )
}
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: (query: string) => ({
    matches: evaluateMedia(query),
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  }),
})
class ResizeObserverStub {
  observe() {}
  unobserve() {}
  disconnect() {}
}
window.ResizeObserver = ResizeObserverStub
window.HTMLElement.prototype.scrollIntoView = () => {}
// Mantine's autosize textarea re-measures when web fonts finish loading.
Object.defineProperty(document, 'fonts', {
  configurable: true,
  value: { ready: Promise.resolve(), addEventListener: () => {}, removeEventListener: () => {} },
})
window.HTMLElement.prototype.scrollTo = () => {}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => {
  cleanup()
  server.resetHandlers()
})
afterAll(() => server.close())
