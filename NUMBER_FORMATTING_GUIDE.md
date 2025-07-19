# Number Formatting Guide

## Overview
Panduan untuk memformat angka di aplikasi menggunakan format Indonesia (###,###).

## Format yang Digunakan

### Format Indonesia
- **Pemisah Ribuan**: Titik (.)
- **Pemisah Desimal**: Koma (,)
- **Contoh**: 1.234.567,89

### Format JavaScript
```javascript
number.toLocaleString('id-ID')
```

## Implementasi di Vue Components

### 1. Fungsi Format Number
```javascript
/**
 * Format angka dengan pemisah ribuan menggunakan format Indonesia
 * Contoh: 125836 -> 125.836
 * @param {number} number - Angka yang akan diformat
 * @returns {string} Angka yang sudah diformat
 */
function formatNumber(number) {
  return number.toLocaleString('id-ID');
}
```

### 2. Penggunaan di Template
```vue
<template>
  <!-- Format statistik -->
  <p class="text-2xl font-bold">{{ formatNumber(stats.total_members) }}</p>
  
  <!-- Format angka di tabel -->
  <td>{{ formatNumber(member.usia) }} tahun</td>
  
  <!-- Format currency -->
  <td>Rp {{ formatNumber(member.saldo) }}</td>
</template>
```

## Contoh Format

| Angka Asli | Format Indonesia | Keterangan |
|------------|------------------|------------|
| 0 | 0 | Angka nol |
| 123 | 123 | Angka kecil |
| 1234 | 1.234 | Ribuan |
| 12345 | 12.345 | Puluh ribuan |
| 123456 | 123.456 | Ratus ribuan |
| 1234567 | 1.234.567 | Jutaan |
| 12345678 | 12.345.678 | Puluh juta |
| 123456789 | 123.456.789 | Ratus juta |

## Aplikasi di Komponen Member

### Statistics Cards
```vue
<!-- Total Member -->
<p class="text-2xl font-bold text-gray-800">{{ formatNumber(stats.total_members) }}</p>

<!-- Active Members -->
<p class="text-2xl font-bold text-gray-800">{{ formatNumber(stats.active_members) }}</p>

<!-- Blocked Members -->
<p class="text-2xl font-bold text-gray-800">{{ formatNumber(stats.blocked_members) }}</p>

<!-- Exclusive Members -->
<p class="text-2xl font-bold text-gray-800">{{ formatNumber(stats.exclusive_members) }}</p>
```

### Table Data
```vue
<!-- Usia member -->
<td>{{ formatNumber(member.usia) }} tahun</td>

<!-- Saldo member -->
<td>Rp {{ formatNumber(member.saldo) }}</td>

<!-- Point member -->
<td>{{ formatNumber(member.point) }} pts</td>
```

## Format Currency

### Rupiah Format
```javascript
function formatCurrency(amount) {
  return `Rp ${amount.toLocaleString('id-ID')}`;
}

// Penggunaan
formatCurrency(125836) // "Rp 125.836"
```

### Currency dengan Desimal
```javascript
function formatCurrencyWithDecimal(amount) {
  return `Rp ${amount.toLocaleString('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })}`;
}

// Penggunaan
formatCurrencyWithDecimal(125836.50) // "Rp 125.836,50"
```

## Format Percentage

### Percentage Format
```javascript
function formatPercentage(value, total) {
  const percentage = (value / total) * 100;
  return `${percentage.toFixed(1)}%`;
}

// Penggunaan
formatPercentage(50, 100) // "50.0%"
```

## Format Date dengan Angka

### Tanggal Indonesia
```javascript
function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  });
}

// Penggunaan
formatDate('2024-01-15') // "15 Januari 2024"
```

## Global Mixin (Opsional)

### Membuat Mixin untuk Reusability
```javascript
// mixins/numberFormat.js
export default {
  methods: {
    formatNumber(number) {
      return number.toLocaleString('id-ID');
    },
    
    formatCurrency(amount) {
      return `Rp ${amount.toLocaleString('id-ID')}`;
    },
    
    formatPercentage(value, total) {
      const percentage = (value / total) * 100;
      return `${percentage.toFixed(1)}%`;
    }
  }
}
```

### Penggunaan Mixin
```vue
<script>
import NumberFormatMixin from '@/mixins/numberFormat.js';

export default {
  mixins: [NumberFormatMixin],
  // ... rest of component
}
</script>
```

## Testing Format Number

### Unit Test
```javascript
// tests/unit/numberFormat.test.js
import { formatNumber } from '@/utils/numberFormat';

describe('Number Format', () => {
  test('formats thousands correctly', () => {
    expect(formatNumber(1234)).toBe('1.234');
    expect(formatNumber(12345)).toBe('12.345');
    expect(formatNumber(123456)).toBe('123.456');
  });
  
  test('handles zero correctly', () => {
    expect(formatNumber(0)).toBe('0');
  });
  
  test('handles large numbers correctly', () => {
    expect(formatNumber(1234567)).toBe('1.234.567');
  });
});
```

## Performance Considerations

### Caching Format
```javascript
// Cache formatted numbers untuk performa
const formatCache = new Map();

function formatNumberCached(number) {
  if (formatCache.has(number)) {
    return formatCache.get(number);
  }
  
  const formatted = number.toLocaleString('id-ID');
  formatCache.set(number, formatted);
  return formatted;
}
```

### Debounced Format untuk Input
```javascript
import { debounce } from 'lodash';

const debouncedFormat = debounce((value) => {
  return formatNumber(value);
}, 300);
```

## Browser Compatibility

### Supported Browsers
- ✅ Chrome 24+
- ✅ Firefox 29+
- ✅ Safari 10+
- ✅ Edge 12+

### Fallback untuk Browser Lama
```javascript
function formatNumberWithFallback(number) {
  if (typeof number.toLocaleString === 'function') {
    return number.toLocaleString('id-ID');
  }
  
  // Fallback untuk browser lama
  return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
```

## Best Practices

### 1. Konsistensi
- Gunakan format yang sama di seluruh aplikasi
- Tetapkan standar format di dokumentasi

### 2. Performance
- Cache hasil format untuk angka yang sering digunakan
- Gunakan debounce untuk input real-time

### 3. Accessibility
- Pastikan format mudah dibaca oleh screen reader
- Gunakan `aria-label` untuk informasi tambahan

### 4. Internationalization
- Siapkan format untuk locale lain jika diperlukan
- Gunakan library seperti `vue-i18n` untuk format yang kompleks

## Contoh Implementasi Lengkap

### Component dengan Format Number
```vue
<template>
  <div class="stats-cards">
    <div class="stat-card">
      <div class="stat-icon">
        <i class="fa-solid fa-users"></i>
      </div>
      <div class="stat-content">
        <h3>Total Member</h3>
        <p class="stat-number">{{ formatNumber(stats.total) }}</p>
      </div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon">
        <i class="fa-solid fa-user-check"></i>
      </div>
      <div class="stat-content">
        <h3>Member Aktif</h3>
        <p class="stat-number">{{ formatNumber(stats.active) }}</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      stats: {
        total: 125836,
        active: 98765
      }
    };
  },
  methods: {
    formatNumber(number) {
      return number.toLocaleString('id-ID');
    }
  }
};
</script>
```

---

**Status**: ✅ Implemented  
**Last Updated**: January 2024  
**Version**: 1.0.0 