/**
 * Klasifikasi Total Skor KPI:
 * - EXCELLENCE: 91–100%
 * - SATISFACTORY: 85–90%
 * - TO IMPROVE: < 85%
 */
export function resolveKpiTotalScoreFlag(scoreValue) {
  if (scoreValue === null || scoreValue === undefined || scoreValue === '') {
    return null;
  }

  const score = Number(scoreValue);
  if (Number.isNaN(score)) {
    return null;
  }

  if (score >= 91) {
    return {
      key: 'excellence',
      label: 'EXCELLENCE',
      className: 'bg-emerald-100 text-emerald-700',
      scoreClassName: 'text-emerald-600',
    };
  }

  if (score >= 85) {
    return {
      key: 'satisfactory',
      label: 'SATISFACTORY',
      className: 'bg-yellow-100 text-yellow-700',
      scoreClassName: 'text-yellow-600',
    };
  }

  return {
    key: 'to_improve',
    label: 'TO IMPROVE',
    className: 'bg-rose-100 text-rose-700',
    scoreClassName: 'text-rose-600',
  };
}
