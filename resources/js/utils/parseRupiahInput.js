/**
 * Parse free-text / API Rupiah value to whole rupiah (integer).
 * Handles: 2750000, 2.750.000, 2.750.000,00, Rp 2.000.000, 2000000.00
 */
export function parseRupiahInput(value) {
  if (value == null || value === '') return NaN

  if (typeof value === 'number' && Number.isFinite(value)) {
    return Math.round(value)
  }

  let s = String(value).trim()
  if (!s) return NaN

  s = s.replace(/^Rp\.?\s*/i, '').trim()

  // Western decimal: 2000000.00 (single dot — NOT Indonesian thousands)
  if (/^\d+\.\d{1,2}$/.test(s)) {
    return Math.round(parseFloat(s))
  }

  // Indonesian: comma as decimal separator — ignore fractional part
  const commaIdx = s.lastIndexOf(',')
  if (commaIdx >= 0) {
    const intPart = s.slice(0, commaIdx).replace(/[\s.]/g, '')
    if (!/^\d+$/.test(intPart)) return NaN
    const n = Number(intPart)
    return Number.isSafeInteger(n) ? n : NaN
  }

  // Indonesian thousand dots or plain digits
  s = s.replace(/[\s.]/g, '')
  if (!/^\d+$/.test(s)) return NaN
  const n = Number(s)
  return Number.isSafeInteger(n) ? n : NaN
}

/** Safe string for kasbon approver input — digits only, no grouping/decimals */
export function formatKasbonAmountForInput(value) {
  const n = parseRupiahInput(value)
  if (!Number.isFinite(n) || n <= 0) return ''
  return String(n)
}
