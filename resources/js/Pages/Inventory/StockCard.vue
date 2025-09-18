<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Kartu Stok (Stock Card)</h1>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input v-model="search" type="text" placeholder="Cari barang, warehouse, referensi, keterangan..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />
        <div class="flex items-center gap-2">
          <label class="text-sm">Warehouse</label>
          <select v-model="selectedWarehouse" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Warehouse</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Barang</label>
          <Multiselect
            v-model="selectedItem"
            :options="items"
            :searchable="true"
            :close-on-select="true"
            :clear-on-select="false"
            :preserve-search="true"
            placeholder="Pilih atau cari barang..."
            track-by="name"
            label="name"
            :preselect-first="false"
            class="w-64"
          />
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Tampilkan</label>
          <select v-model="perPage" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
          </select>
          <span class="text-sm">data</span>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Periode</label>
          <input type="date" v-model="fromDate" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500" />
          <span>-</span>
          <input type="date" v-model="toDate" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <button @click="reloadData" :disabled="loadingReload" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md font-semibold hover:bg-blue-700">
          <span v-if="loadingReload" class="animate-spin mr-2"><i class="fas fa-spinner"></i></span>
          <span v-else class="mr-2"><i class="fas fa-sync-alt"></i></span>
          Load Data
        </button>
      </div>
      <div v-if="props.error" class="bg-red-50 border-l-4 border-red-400 text-red-800 p-4 rounded my-8 text-center font-semibold">
        {{ props.error }}
      </div>
      <div v-else-if="!selectedItem" class="bg-blue-50 border-l-4 border-blue-400 text-blue-800 p-4 rounded my-8 text-center font-semibold">
        <i class="fas fa-info-circle mr-2"></i>
        Silakan pilih warehouse, barang, dan periode tanggal, kemudian klik tombol "Load Data" untuk melihat kartu stok.
      </div>
      <template v-else-if="selectedItem && cards.length > 0">
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50 sticky top-0 z-10">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
                  <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Masuk (Qty)</th>
                  <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Keluar (Qty)</th>
                  <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Saldo (Qty)</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Referensi</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Keterangan</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="!filteredCards.length">
                  <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data kartu stok.</td>
                </tr>
                <tr v-for="(row, index) in paginatedCards" :key="row.id" :class="[ 'hover:bg-gray-50 transition', index === paginatedCards.length - 1 ? 'bg-yellow-200 font-bold' : '' ]">
                  <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.date ? new Date(row.date).toLocaleDateString('id-ID') : '-' }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.warehouse_name }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ formatQty(row, 'in') }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ formatQty(row, 'out') }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ formatSaldoQty(row) }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <template v-if="row.reference_number">
                      {{ row.reference_number }}
                    </template>
                    <template v-else>
                      {{ row.reference_type ? row.reference_type + (row.reference_id ? ' #' + row.reference_id : '') : '-' }}
                    </template>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.description || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="flex justify-between items-center mt-4" v-if="filteredCards.length">
          <div class="text-sm text-gray-600">
            Menampilkan {{ startIndex + 1 }} - {{ endIndex }} dari {{ filteredCards.length }} data
          </div>
          <div class="flex gap-1">
            <button @click="prevPage" :disabled="page === 1" class="px-3 py-1 rounded border text-sm" :class="page === 1 ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&lt;</button>
            <span class="px-2">Halaman {{ page }} / {{ totalPages }}</span>
            <button @click="nextPage" :disabled="page === totalPages" class="px-3 py-1 rounded border text-sm" :class="page === totalPages ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&gt;</button>
          </div>
        </div>
      </template>
      <div v-else-if="selectedItem && cards.length === 0" class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 p-4 rounded my-8 text-center font-semibold">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        Tidak ada data kartu stok untuk item yang dipilih. Coba ubah filter warehouse atau periode tanggal.
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
const props = defineProps({
  cards: Array,
  warehouses: Array,
  items: Array,
  saldo_awal: Object,
  error: String
});
const search = ref('');
const perPage = ref(25);
const page = ref(1);
const selectedWarehouse = ref('');
const selectedItem = ref('');
const loadingReload = ref(false)
const fromDate = ref('');
const toDate = ref('');

function getQtyByPriority(row, type) {
  // type: 'in', 'out', 'saldo'
  let smallConv = row.small_conversion_qty || 1;
  let mediumConv = row.medium_conversion_qty || 1;
  let small = row[type + '_qty_small'] || 0;
  let medium = row[type + '_qty_medium'] || 0;
  let large = row[type + '_qty_large'] || 0;
  let totalSmall = 0;
  if (small > 0) {
    totalSmall = small;
  } else if (medium > 0) {
    totalSmall = medium * smallConv;
  } else if (large > 0) {
    totalSmall = large * smallConv * mediumConv;
  } else {
    return '-';
  }
  let largeQty = (smallConv > 0 && mediumConv > 0) ? Math.floor(totalSmall / (smallConv * mediumConv)) : 0;
  let sisaAfterLarge = (smallConv > 0 && mediumConv > 0) ? totalSmall % (smallConv * mediumConv) : totalSmall;
  let mediumQty = smallConv > 0 ? Math.floor(sisaAfterLarge / smallConv) : 0;
  let smallQty = smallConv > 0 ? sisaAfterLarge % smallConv : sisaAfterLarge;
  return [
    largeQty > 0 ? `${largeQty} ${row.large_unit_name}` : null,
    mediumQty > 0 ? `${mediumQty} ${row.medium_unit_name}` : null,
    smallQty > 0 ? `${smallQty} ${row.small_unit_name}` : null
  ].filter(Boolean).join(' / ') || '-';
}

function getSaldoQty(row) {
  let smallConv = row.small_conversion_qty || 1;
  let mediumConv = row.medium_conversion_qty || 1;
  // Cari basis qty masuk
  let inSmall = row.in_qty_small || 0;
  let inMedium = row.in_qty_medium || 0;
  let inLarge = row.in_qty_large || 0;
  let totalIn = 0;
  if (inSmall > 0) {
    totalIn = inSmall;
  } else if (inMedium > 0) {
    totalIn = inMedium * smallConv;
  } else if (inLarge > 0) {
    totalIn = inLarge * smallConv * mediumConv;
  } else {
    totalIn = 0;
  }
  // Cari basis qty keluar
  let outSmall = row.out_qty_small || 0;
  let outMedium = row.out_qty_medium || 0;
  let outLarge = row.out_qty_large || 0;
  let totalOut = 0;
  if (outSmall > 0) {
    totalOut = outSmall;
  } else if (outMedium > 0) {
    totalOut = outMedium * smallConv;
  } else if (outLarge > 0) {
    totalOut = outLarge * smallConv * mediumConv;
  } else {
    totalOut = 0;
  }
  let saldoSmall = totalIn - totalOut;
  if (saldoSmall <= 0) return '-';
  let largeQty = (smallConv > 0 && mediumConv > 0) ? Math.floor(saldoSmall / (smallConv * mediumConv)) : 0;
  let sisaAfterLarge = (smallConv > 0 && mediumConv > 0) ? saldoSmall % (smallConv * mediumConv) : saldoSmall;
  let mediumQty = smallConv > 0 ? Math.floor(sisaAfterLarge / smallConv) : 0;
  let smallQty = smallConv > 0 ? sisaAfterLarge % smallConv : sisaAfterLarge;
  return [
    largeQty > 0 ? `${largeQty} ${row.large_unit_name}` : null,
    mediumQty > 0 ? `${mediumQty} ${row.medium_unit_name}` : null,
    smallQty > 0 ? `${smallQty} ${row.small_unit_name}` : null
  ].filter(Boolean).join(' / ') || '-';
}

const filteredCards = computed(() => {
  let data = props.cards;
  if (selectedWarehouse.value) {
    data = data.filter(row => String(row.warehouse_name) === String(props.warehouses.find(w => w.id == selectedWarehouse.value)?.name));
  }
  if (selectedItem.value) {
    data = data.filter(row => row.item_name === selectedItem.value.name);
  }
  if (fromDate.value) {
    data = data.filter(row => new Date(row.date) >= new Date(fromDate.value));
  }
  if (toDate.value) {
    data = data.filter(row => new Date(row.date) <= new Date(toDate.value));
  }
  if (!search.value) return data;
  const s = search.value.toLowerCase();
  return data.filter(row =>
    (row.item_name && row.item_name.toLowerCase().includes(s)) ||
    (row.warehouse_name && row.warehouse_name.toLowerCase().includes(s)) ||
    (row.reference_type && row.reference_type.toLowerCase().includes(s)) ||
    (row.description && row.description.toLowerCase().includes(s))
  );
});

const totalPages = computed(() => Math.ceil(filteredCards.value.length / perPage.value) || 1);
const startIndex = computed(() => (page.value - 1) * perPage.value);
const endIndex = computed(() => Math.min(startIndex.value + perPage.value, filteredCards.value.length));
const paginatedCards = computed(() => filteredCards.value.slice(startIndex.value, endIndex.value));

function prevPage() {
  if (page.value > 1) page.value--;
}
function nextPage() {
  if (page.value < totalPages.value) page.value++;
}
watch([perPage, search], () => { page.value = 1; });

function convertToUnits(totalSmall, smallConv, mediumConv, largeUnit, mediumUnit, smallUnit) {
  let large = 0, medium = 0, small = 0;
  if (smallConv && mediumConv) {
    large = Math.floor(totalSmall / (smallConv * mediumConv));
    let sisaAfterLarge = totalSmall % (smallConv * mediumConv);
    medium = Math.floor(sisaAfterLarge / smallConv);
    small = sisaAfterLarge % smallConv;
  } else if (smallConv) {
    medium = Math.floor(totalSmall / smallConv);
    small = totalSmall % smallConv;
  } else {
    small = totalSmall;
  }
  let result = [];
  if (large > 0) result.push(`${large} ${largeUnit || ''}`.trim());
  if (medium > 0) result.push(`${medium} ${mediumUnit || ''}`.trim());
  if (small > 0) result.push(`${small} ${smallUnit || ''}`.trim());
  return result.length ? result.join(' / ') : `0 ${smallUnit || ''}`;
}

function formatNumber(val) {
  if (val == null) return 0;
  if (Number(val) % 1 === 0) return Number(val);
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatQty(row, type = null) {
  if (type === 'in') {
    return `${formatNumber(row.in_qty_small)} ${row.small_unit_name || ''} | ${formatNumber(row.in_qty_medium)} ${row.medium_unit_name || ''} | ${formatNumber(row.in_qty_large)} ${row.large_unit_name || ''}`;
  } else if (type === 'out') {
    return `${formatNumber(row.out_qty_small)} ${row.small_unit_name || ''} | ${formatNumber(row.out_qty_medium)} ${row.medium_unit_name || ''} | ${formatNumber(row.out_qty_large)} ${row.large_unit_name || ''}`;
  } else {
    return '-';
  }
}

function formatSaldoQty(row) {
  return `${formatNumber(row.saldo_qty_small)} ${row.small_unit_name || ''} | ${formatNumber(row.saldo_qty_medium)} ${row.medium_unit_name || ''} | ${formatNumber(row.saldo_qty_large)} ${row.large_unit_name || ''}`;
}

// Hitung saldo berjalan
const runningBalances = computed(() => {
  let saldo = 0;
  return paginatedCards.value.map((row, idx) => {
    let inSmall = row.in_qty_small || 0;
    let inMedium = row.in_qty_medium || 0;
    let inLarge = row.in_qty_large || 0;
    let smallConv = row.small_conversion_qty || 1;
    let mediumConv = row.medium_conversion_qty || 1;
    let totalIn = inSmall + (inMedium * smallConv) + (inLarge * smallConv * mediumConv);

    let outSmall = row.out_qty_small || 0;
    let outMedium = row.out_qty_medium || 0;
    let outLarge = row.out_qty_large || 0;
    let totalOut = outSmall + (outMedium * smallConv) + (outLarge * smallConv * mediumConv);

    saldo += totalIn - totalOut;
    // Debug log
    console.log('runningBalance', { idx, saldo, row });
    return saldo;
  });
});

// Tambahkan summary saldo
const saldoAwal = computed(() => {
  if (props.saldo_awal) {
    return props.saldo_awal;
  }
  if (!selectedItem.value) return '-';
  let data = props.cards.filter(row => row.item_name === selectedItem.value.name);
  if (selectedWarehouse.value) {
    data = data.filter(row => String(row.warehouse_name) === String(props.warehouses.find(w => w.id == selectedWarehouse.value)?.name));
  }
  if (fromDate.value) {
    data = data.filter(row => new Date(row.date) < new Date(fromDate.value));
  }
  // Urutkan data berdasarkan tanggal dan id
  data = [...data].sort((a, b) => new Date(a.date) - new Date(b.date) || a.id - b.id);
  let saldo = 0;
  let smallConv = data[0]?.small_conversion_qty || 1;
  let mediumConv = data[0]?.medium_conversion_qty || 1;
  data.forEach(row => {
    let inSmall = (row.in_qty_small || 0) + (row.in_qty_medium || 0) * smallConv + (row.in_qty_large || 0) * smallConv * mediumConv;
    let outSmall = (row.out_qty_small || 0) + (row.out_qty_medium || 0) * smallConv + (row.out_qty_large || 0) * smallConv * mediumConv;
    saldo += inSmall - outSmall;
  });
  const toUnit = (val) => convertToUnits(val, smallConv, mediumConv, data[0]?.large_unit_name, data[0]?.medium_unit_name, data[0]?.small_unit_name);
  return toUnit(saldo);
});

const summary = computed(() => {
  if (!selectedItem.value) return { saldoAwal: '-', totalMasuk: '-', totalKeluar: '-', saldoAkhir: '-' };
  let data = filteredCards.value;
  let smallConv = data[0]?.small_conversion_qty || 1;
  let mediumConv = data[0]?.medium_conversion_qty || 1;
  let totalMasuk = 0, totalKeluar = 0, saldoAkhir = 0;
  data.forEach(row => {
    let inSmall = (row.in_qty_small || 0) + (row.in_qty_medium || 0) * smallConv + (row.in_qty_large || 0) * smallConv * mediumConv;
    let outSmall = (row.out_qty_small || 0) + (row.out_qty_medium || 0) * smallConv + (row.out_qty_large || 0) * smallConv * mediumConv;
    totalMasuk += inSmall;
    totalKeluar += outSmall;
    saldoAkhir += inSmall - outSmall;
  });
  // saldoAkhir = saldoAwal + totalMasuk - totalKeluar
  let saldoAwalVal = saldoAwal.value === '-' ? 0 : saldoAwal.value;
  let saldoAkhirTotal = saldoAwalVal;
  if (typeof saldoAkhir === 'number') saldoAkhirTotal += saldoAkhir;
  const toUnit = (val) => convertToUnits(val, smallConv, mediumConv, data[0]?.large_unit_name, data[0]?.medium_unit_name, data[0]?.small_unit_name);
  return {
    saldoAwal: saldoAwal.value,
    totalMasuk: toUnit(totalMasuk),
    totalKeluar: toUnit(totalKeluar),
    saldoAkhir: toUnit(saldoAkhirTotal),
  };
});

function reloadData() {
  // Validasi: harus ada item yang dipilih
  if (!selectedItem.value) {
    alert('Silakan pilih barang terlebih dahulu!');
    return;
  }
  
  loadingReload.value = true
  
  // Prepare parameters
  const params = {
    item_id: selectedItem.value?.id || '',
    warehouse_id: selectedWarehouse.value || '',
    from: fromDate.value || '',
    to: toDate.value || ''
  }
  
  // Remove empty parameters
  Object.keys(params).forEach(key => {
    if (!params[key]) {
      delete params[key]
    }
  })
  
  // Make request to server
  router.get('/inventory/stock-card', params, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      loadingReload.value = false
    },
    onError: (errors) => {
      loadingReload.value = false
      console.error('Error loading data:', errors)
    }
  })
}
</script>

<style scoped>
/* Custom styling for vue-multiselect */
:deep(.multiselect) {
  min-height: 38px;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
}

:deep(.multiselect:focus-within) {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

:deep(.multiselect__placeholder) {
  color: #6b7280;
  font-size: 0.875rem;
  padding: 8px 12px;
}

:deep(.multiselect__single) {
  padding: 8px 12px;
  font-size: 0.875rem;
  color: #374151;
}

:deep(.multiselect__input) {
  padding: 8px 12px;
  font-size: 0.875rem;
}

:deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

:deep(.multiselect__option) {
  padding: 8px 12px;
  font-size: 0.875rem;
}

:deep(.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}

:deep(.multiselect__option--selected) {
  background: #dbeafe;
  color: #1e40af;
}
</style>