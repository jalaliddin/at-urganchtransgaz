/*
 * AT.urtg.uz design system — visual direction
 *
 * Subject: internal IT service management for "Urganchtransgaz" (gas transmission, Khorezm).
 * Palette is taken from Khiva's majolica tiles, the region's own visual vernacular, which
 * also sits close to a gas-flame blue:
 *   cobalt    #1D4E89  primary actions, active navigation, links
 *   turquoise #0E8C99  accent: focus/active markers, highlights in the login girih pattern
 *   ink       #1A2433  text (cool, never a tinted near-black)
 *   surface   #F4F6F9  page background (cool grey instead of a warm cream)
 *   line      #D9E1EB  borders and dividers
 * Status colours stay conventional (green / yellow / red / grey) so meaning is never decorative.
 *
 * Type: IBM Plex Sans (industrial/engineering, Latin Extended incl. ʻ U+02BB, Cyrillic for ru).
 * IBM Plex Mono only where the content is an identifier: codes, personnel numbers, request IDs.
 *
 * Principles: one memorable element — the girih star pattern on the auth screens; the app
 * shell stays quiet, left-aligned and dense enough for daily operational work. Radius grows
 * with hierarchy (controls 4px, panels 8px). No decorative motion; sentence case labels.
 */
import { createTheme, type MantineColorsTuple } from '@mantine/core'

const cobalt: MantineColorsTuple = [
  '#eef3fa', '#d9e3f2', '#b0c6e4', '#84a6d5', '#5f8bc8',
  '#4679bf', '#386fbc', '#2a5ea6', '#1d4e89', '#103f72',
]

const turquoise: MantineColorsTuple = [
  '#e4f8fa', '#d2eff2', '#a5dde3', '#74cbd4', '#4cbcc7',
  '#33b2bf', '#22adbb', '#0f98a5', '#0e8c99', '#00727d',
]

const slate: MantineColorsTuple = [
  '#f4f6f9', '#e9edf2', '#d9e1eb', '#c3cdd9', '#9aa8b8',
  '#7a8899', '#5c6a7b', '#465365', '#2f3a4a', '#1a2433',
]

const dark: MantineColorsTuple = [
  '#c9d1dc', '#a7b2c1', '#7d8898', '#556173', '#3a4556',
  '#2a3442', '#1f2834', '#18202a', '#121820', '#0c1117',
]

export const theme = createTheme({
  primaryColor: 'cobalt',
  primaryShade: { light: 8, dark: 5 },
  colors: { cobalt, turquoise, gray: slate, dark },
  black: '#1a2433',
  fontFamily: '"IBM Plex Sans", "Segoe UI", system-ui, sans-serif',
  fontFamilyMonospace: '"IBM Plex Mono", ui-monospace, monospace',
  headings: {
    fontFamily: '"IBM Plex Sans", "Segoe UI", system-ui, sans-serif',
    fontWeight: '600',
    sizes: {
      h1: { fontSize: '1.75rem', lineHeight: '1.25' },
      h2: { fontSize: '1.375rem', lineHeight: '1.3' },
      h3: { fontSize: '1.125rem', lineHeight: '1.35' },
    },
  },
  defaultRadius: 'sm',
  radius: { xs: '2px', sm: '4px', md: '8px', lg: '12px', xl: '16px' },
  cursorType: 'pointer',
  focusRing: 'auto',
  components: {
    Paper: { defaultProps: { radius: 'md' } },
    Card: { defaultProps: { radius: 'md' } },
    Modal: { defaultProps: { radius: 'md', centered: true } },
    Drawer: { defaultProps: { position: 'right', size: 'lg' } },
    Tooltip: { defaultProps: { withArrow: true, openDelay: 300 } },
  },
})
