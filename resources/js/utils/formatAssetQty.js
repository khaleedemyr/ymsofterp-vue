export function formatAssetQty(val) {
    if (val === null || val === undefined || val === '') return '-';
    const n = Number(val);
    if (Number.isNaN(n)) return String(val);
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 4,
    }).format(n);
}
