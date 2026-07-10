/**
 * Skor key strategy = rata-rata tertimbang skor KPI dalam strategy (skala 0–100).
 */
export function calculateKpiStrategyScore(items = []) {
  if (!Array.isArray(items) || items.length === 0) {
    return null;
  }

  let weighted = 0;
  let totalWeight = 0;

  for (const item of items) {
    if (item?.score === null || item?.score === undefined || item?.score === '') {
      continue;
    }

    const score = Number(item.score);
    const weight = Number(item.weight_percent || 0);
    if (!Number.isFinite(score) || weight <= 0) {
      continue;
    }

    weighted += score * weight;
    totalWeight += weight;
  }

  if (totalWeight <= 0) {
    return null;
  }

  return Math.round((weighted / totalWeight) * 100) / 100;
}

/**
 * Kontribusi strategy terhadap total skor evaluasi.
 */
export function calculateKpiStrategyContribution(items = []) {
  if (!Array.isArray(items) || items.length === 0) {
    return null;
  }

  let hasValue = false;
  let total = 0;

  for (const item of items) {
    if (item?.weighted_score === null || item?.weighted_score === undefined || item?.weighted_score === '') {
      continue;
    }

    const value = Number(item.weighted_score);
    if (!Number.isFinite(value)) {
      continue;
    }

    hasValue = true;
    total += value;
  }

  if (!hasValue) {
    return null;
  }

  return Math.round(total * 100) / 100;
}
