export const REGIONAL_DEPARTMENTS = [
  { key: 'Bar', label: 'Bar', icon: 'fa-martini-glass-citrus' },
  { key: 'Kitchen', label: 'Kitchen', icon: 'fa-kitchen-set' },
  { key: 'Service', label: 'Service', icon: 'fa-bell-concierge' },
]

export const REGIONAL_AREA_KEYS = REGIONAL_DEPARTMENTS.map((d) => d.key)

export function getAreaLabel(area) {
  return REGIONAL_DEPARTMENTS.find((d) => d.key === area)?.label || area || '-'
}

export function formatAreasLabel(areas) {
  if (!Array.isArray(areas) || areas.length === 0) {
    return '—'
  }

  return areas.map((area) => getAreaLabel(area)).join(', ')
}

export function resolveUserAreas(user) {
  if (Array.isArray(user?.areas) && user.areas.length > 0) {
    return user.areas
  }

  return user?.area ? [user.area] : []
}
