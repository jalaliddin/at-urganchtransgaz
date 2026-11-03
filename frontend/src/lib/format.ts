import dayjs, { APP_TIMEZONE } from './dayjs'

const DASH = '—'
const NBSP = '\u00a0'
const DATE_ONLY = /^\d{4}-\d{2}-\d{2}$/

/** DD.MM.YYYY. Calendar dates (YYYY-MM-DD) are not shifted; timestamps are shown in Tashkent. */
export function formatDate(value: string | null | undefined): string {
  if (!value) return DASH
  if (DATE_ONLY.test(value)) return dayjs(value, 'YYYY-MM-DD').format('DD.MM.YYYY')
  return dayjs.utc(value).tz(APP_TIMEZONE).format('DD.MM.YYYY')
}

/** DD.MM.YYYY HH:mm in Asia/Tashkent. */
export function formatDateTime(value: string | null | undefined): string {
  if (!value) return DASH
  return dayjs.utc(value).tz(APP_TIMEZONE).format('DD.MM.YYYY HH:mm')
}

function groupThousands(digits: string): string {
  return digits.replace(/\B(?=(\d{3})+(?!\d))/g, NBSP)
}

export function formatNumber(value: number | null | undefined): string {
  if (value === null || value === undefined || Number.isNaN(value)) return DASH
  const sign = value < 0 ? '-' : ''
  return sign + groupThousands(String(Math.trunc(Math.abs(value))))
}

/**
 * UZS amounts. The API sends money as decimal strings ("1250000.00") so no
 * floating-point rounding happens on the way.
 */
export function formatMoney(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return DASH
  const text = typeof value === 'number' ? value.toFixed(2) : value
  const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(text.trim())
  if (!match) return DASH
  const [, sign = '', whole = '0', fraction = ''] = match
  return `${sign}${groupThousands(whole)},${fraction.padEnd(2, '0').slice(0, 2)}${NBSP}so'm`
}
