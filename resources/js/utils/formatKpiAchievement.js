import { formatKpiNumber } from '@/utils/formatKpiNumber';

/**
 * Format nilai achievement KPI dengan satuan yang sesuai (bukan selalu %).
 *
 * @param {number|null|undefined} value
 * @param {{ value_type?: string, unit_suffix?: string, unit_label?: string }|null|undefined} meta
 * @param {string} empty
 */
export function formatKpiAchievement(value, meta, empty = '—') {
  if (value === null || value === undefined || value === '') {
    return empty;
  }

  const formatted = formatKpiNumber(value, empty);
  if (formatted === empty) {
    return empty;
  }

  const valueType = meta?.value_type || 'percent';
  const suffix = meta?.unit_suffix ?? (valueType === 'percent' ? '%' : '');
  const label = meta?.unit_label || '';

  if (suffix) {
    return `${formatted}${suffix}`;
  }

  if (label) {
    return `${formatted} ${label}`;
  }

  return formatted;
}

/**
 * Satuan tampilan untuk input manual parameter D*.
 */
export function formatKpiParameterInputUnit(pv) {
  if (!pv) return '';

  const dataType = pv.data_type || 'decimal';
  const code = String(pv.parameter_code || '').toUpperCase();
  const name = String(pv.parameter_name || '').toLowerCase();

  if (dataType === 'percent') {
    return '%';
  }

  if (dataType === 'hours') {
    return 'jam';
  }

  if (dataType === 'integer') {
    if (code === 'D032' || name.includes('coaching') || name.includes('person')) {
      return 'orang';
    }
    if (name.includes('visit') && name.includes('count')) {
      return 'hari';
    }
    if (name.includes('product') || name.includes('benchmark') || name.includes('npd')) {
      return 'produk';
    }
    return '';
  }

  if (name.includes('minute')) {
    return 'menit';
  }

  if (name.includes('rating') || name.includes('google review')) {
    return '/ 5';
  }

  return '';
}

export function kpiParameterInputMode(pv) {
  const dataType = pv?.data_type || 'decimal';
  if (dataType === 'integer') {
    return 'numeric';
  }
  return 'decimal';
}
