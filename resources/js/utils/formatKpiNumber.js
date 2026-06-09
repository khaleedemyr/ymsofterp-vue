/**
 * Format angka KPI (locale id-ID).
 */
export function formatKpiNumber(val, empty = '—') {
  if (val === null || val === undefined || val === '') {
    return empty;
  }

  const n = Number(val);
  if (!Number.isFinite(n)) {
    return empty;
  }

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(n);
}

/**
 * Parse input teks ke number (dukung 75, 75,5, 1.234.567,89).
 */
export function parseKpiNumber(str) {
  if (str === null || str === undefined || str === '') {
    return null;
  }

  const s = String(str).trim().replace(/\s/g, '');
  if (!s) {
    return null;
  }

  let normalized = s;
  if (s.includes(',')) {
    normalized = s.replace(/\./g, '').replace(',', '.');
  }

  const n = parseFloat(normalized);
  return Number.isFinite(n) ? n : null;
}

/**
 * Untuk edit: tampilkan angka plain tanpa pemisah ribuan.
 */
export function formatKpiNumberPlain(val) {
  if (val === null || val === undefined || val === '') {
    return '';
  }

  const n = Number(val);
  if (!Number.isFinite(n)) {
    return '';
  }

  return String(n);
}
