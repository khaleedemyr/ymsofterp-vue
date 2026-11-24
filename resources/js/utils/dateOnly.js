/**
 * Tanggal kalender (tanpa jam) — hindari new Date() / toISOString() agar tidak bergeser karena timezone.
 * toISOString() = UTC; sebelum jam 07:00 WIB tanggal bisa H-1.
 */

/** YYYY-MM-DD di zona Asia/Jakarta (sama dengan Bangkok UTC+7). */
export function todayYmdJakarta(date = new Date()) {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(date)
}

export function toDateInputValue(value) {
  if (value == null || value === '') return ''
  const raw = String(value).trim()
  const m = raw.match(/^(\d{4}-\d{2}-\d{2})/)
  return m ? m[1] : ''
}

/** DD/MM/YYYY */
export function formatDateOnlyId(value) {
  const ymd = toDateInputValue(value)
  if (!ymd) return '-'
  const [y, m, d] = ymd.split('-')
  return `${d}/${m}/${y}`
}

const ID_MONTHS = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

/** 20 Agustus 1982 */
export function formatDateOnlyIdLong(value) {
  const ymd = toDateInputValue(value)
  if (!ymd) return 'Tidak ada tanggal'
  const [y, m, d] = ymd.split('-').map((x) => parseInt(x, 10))
  const month = ID_MONTHS[m - 1]
  if (!month || !y || !d) return ymd
  return `${d} ${month} ${y}`
}
