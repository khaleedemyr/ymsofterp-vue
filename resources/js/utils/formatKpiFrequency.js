const FREQUENCY_LABELS = {
  monthly: 'Monthly',
  quarterly: 'Quarterly',
};

export function formatKpiFrequencyLabel(value) {
  if (!value) return 'Monthly';
  return FREQUENCY_LABELS[value] || value;
}

export function kpiFrequencyBadgeClass(value) {
  const map = {
    monthly: 'bg-sky-100 text-sky-800',
    quarterly: 'bg-violet-100 text-violet-800',
  };
  return map[value] || 'bg-gray-100 text-gray-700';
}
