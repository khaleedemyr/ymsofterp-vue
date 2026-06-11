/**
 * Parse free-text Rupiah input (Indonesian grouping) to whole rupiah amount.
 * "2.000.000", "2750000", "Rp 2.000.000" → 2000000 / 2750000
 * "2.000.000,00" must NOT become 200000000 (bug when stripping all non-digits).
 */
export function parseRupiahInput(value) {
  if (value == null || value === '') return NaN

  let s = String(value).trim()
  if (!s) return NaN

  s = s.replace(/^Rp\.?\s*/i, '').trim()

  const commaIdx = s.lastIndexOf(',')
  if (commaIdx >= 0) {
    s = s.slice(0, commaIdx)
  }

  s = s.replace(/[\s.]/g, '')

  if (!/^\d+$/.test(s)) return NaN

  const n = Number(s)
  return Number.isSafeInteger(n) ? n : NaN
}
