/** Normalisasi jam aturan flow: angka lama (0–23) → HH:mm */
export function normalizeHourRuleValue(value, fallback = '08:00') {
  if (value === undefined || value === null || value === '') {
    return fallback
  }
  if (typeof value === 'number') {
    return `${String(value).padStart(2, '0')}:00`
  }
  const s = String(value).trim()
  if (/^\d{1,2}$/.test(s)) {
    return `${s.padStart(2, '0')}:00`
  }
  if (/^\d{1,2}:\d{2}$/.test(s)) {
    const [h, m] = s.split(':')
    return `${h.padStart(2, '0')}:${m}`
  }
  return fallback
}

export function ensureHourBetweenRule(rule) {
  if (rule?.field !== 'hour_between') {
    return rule
  }
  rule.from = normalizeHourRuleValue(rule.from, '08:00')
  rule.to = normalizeHourRuleValue(rule.to, '17:00')
  return rule
}

export function defaultHourBetweenRule() {
  return { field: 'hour_between', from: '08:00', to: '17:00' }
}
