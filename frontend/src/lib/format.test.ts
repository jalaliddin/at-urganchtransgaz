import { describe, expect, it } from 'vitest'
import { formatDate, formatDateTime, formatMoney, formatNumber } from '@/lib/format'

const plain = (value: string) => value.replace(/\u00a0/g, ' ')

describe('format', () => {
  it('shows timestamps in Asia/Tashkent as DD.MM.YYYY HH:mm', () => {
    expect(formatDateTime('2026-11-02T19:30:00Z')).toBe('03.11.2026 00:30')
  })

  it('formats calendar dates without shifting the day', () => {
    expect(formatDate('2026-11-03')).toBe('03.11.2026')
    expect(formatDate('2026-11-02T19:30:00Z')).toBe('03.11.2026')
  })

  it('uses a dash for missing values', () => {
    expect(formatDate(null)).toBe('—')
    expect(formatDateTime(undefined)).toBe('—')
  })

  it("formats money in UZS with grouped thousands and decimal comma", () => {
    expect(plain(formatMoney('1250000.00'))).toBe("1 250 000,00 so'm")
    expect(plain(formatMoney(-5000))).toBe("-5 000,00 so'm")
  })

  it('groups integers', () => {
    expect(plain(formatNumber(1234567))).toBe('1 234 567')
  })
})
