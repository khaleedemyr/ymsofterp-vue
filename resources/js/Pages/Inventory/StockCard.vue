<template>
  <AppLayout>
    <InventoryReportPage
      eyebrow="Warehouse Inventory"
      title="Kartu Stok Gudang"
      subtitle="Riwayat mutasi stok warehouse. Delivery Order serial dan transaksi SN lain digabung per kedatangan — klik baris SN untuk detail."
      variant="warehouse"
    >
      <template #badges>
        <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold backdrop-blur">
          <i class="fa-solid fa-truck-ramp-box mr-1.5 opacity-80"></i>
          DO Serial support
        </span>
      </template>

      <template v-if="selectedItem && filteredCards.length" #stats>
        <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
          <p class="text-[11px] uppercase tracking-wide text-indigo-200/80">Barang</p>
          <p class="mt-1 truncate text-sm font-bold">{{ selectedItem?.name || '—' }}</p>
        </div>
        <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
          <p class="text-[11px] uppercase tracking-wide text-indigo-200/80">Transaksi</p>
          <p class="mt-1 text-lg font-bold tabular-nums">{{ filteredCards.length }}</p>
        </div>
        <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
          <p class="text-[11px] uppercase tracking-wide text-indigo-200/80">Grup Serial</p>
          <p class="mt-1 text-lg font-bold tabular-nums">{{ serialGroupCount }}</p>
        </div>
        <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
          <p class="text-[11px] uppercase tracking-wide text-indigo-200/80">Periode</p>
          <p class="mt-1 text-xs font-semibold leading-snug">{{ periodLabel }}</p>
        </div>
      </template>

      <template #filters>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
          <div class="xl:col-span-2 field">
            <label>Pencarian</label>
            <input v-model="search" type="text" placeholder="Cari referensi, keterangan..." class="field-input" />
          </div>
          <div class="field">
            <label>Warehouse</label>
            <select v-model="selectedWarehouse" class="field-input">
              <option value="">Semua Warehouse</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div class="field xl:col-span-2">
            <label>Barang</label>
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
            />
          </div>
          <div class="field">
            <label>Dari Tanggal</label>
            <input v-model="fromDate" type="date" class="field-input" />
          </div>
          <div class="field">
            <label>Sampai Tanggal</label>
            <input v-model="toDate" type="date" class="field-input" />
          </div>
          <div class="field">
            <label>Per Halaman</label>
            <select v-model="perPage" class="field-input">
              <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
          <div class="field flex items-end xl:col-span-2">
            <button type="button" class="btn-primary w-full" :disabled="loadingReload" @click="reloadData">
              <i :class="loadingReload ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-database'"></i>
              {{ loadingReload ? 'Memuat...' : 'Tampilkan Data' }}
            </button>
          </div>
        </div>
      </template>

      <div v-if="props.error" class="alert-box alert-error">{{ props.error }}</div>
      <div v-else-if="!selectedItem" class="alert-box alert-info">
        <i class="fa-solid fa-circle-info mr-2"></i>
        Pilih warehouse & barang, lalu klik <strong>Tampilkan Data</strong>.
      </div>
      <div v-else-if="selectedItem && cards.length === 0" class="alert-box alert-warn">
        <i class="fa-solid fa-triangle-exclamation mr-2"></i>
        Tidak ada data kartu stok untuk filter ini.
      </div>

      <template v-else-if="selectedItem && cards.length > 0">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:px-5">
            <p class="text-sm text-slate-600">
              <span class="font-bold text-slate-900">{{ startIndex + 1 }}–{{ endIndex }}</span> / {{ filteredCards.length }} transaksi
            </p>
            <p class="text-xs text-slate-500">Klik baris <span class="font-bold text-violet-600">SN</span> untuk expand nomor seri</p>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
              <thead class="bg-slate-800 text-white">
                <tr>
                  <th class="th-cell">Tanggal</th>
                  <th class="th-cell">Warehouse</th>
                  <th class="th-cell text-right">Masuk</th>
                  <th class="th-cell text-right">Keluar</th>
                  <th class="th-cell text-right">Saldo</th>
                  <th class="th-cell">Referensi</th>
                  <th class="th-cell">Keterangan</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="!filteredCards.length">
                  <td colspan="7" class="px-6 py-12 text-center text-slate-400">Tidak ada data.</td>
                </tr>
                <template v-for="(row, index) in paginatedCards" :key="row.group_key || row.id || index">
                  <tr
                    :class="[
                      index === paginatedCards.length - 1 ? 'bg-amber-50 font-semibold ring-1 ring-inset ring-amber-200/80' : row.is_grouped ? 'cursor-pointer bg-violet-50/30 hover:bg-violet-50/60' : 'hover:bg-slate-50/80',
                      'transition',
                    ]"
                    @click="row.is_grouped ? toggleGroup(row, $event) : null"
                  >
                    <td class="td-cell">
                      <div class="flex items-center gap-2">
                        <span v-if="row.is_grouped" class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-violet-100 text-violet-700">
                          <i :class="isGroupExpanded(row) ? 'fa-solid fa-chevron-down text-[10px]' : 'fa-solid fa-chevron-right text-[10px]'"></i>
                        </span>
                        <span class="font-medium">{{ row.date ? new Date(row.date).toLocaleDateString('id-ID') : '-' }}</span>
                      </div>
                    </td>
                    <td class="td-cell">{{ row.warehouse_name }}</td>
                    <td class="td-cell text-right tabular-nums text-emerald-700">{{ formatQty(row, 'in') }}</td>
                    <td class="td-cell text-right tabular-nums text-rose-700">{{ formatQty(row, 'out') }}</td>
                    <td class="td-cell text-right tabular-nums font-medium">{{ formatSaldoQty(row) }}</td>
                    <td class="td-cell">
                      <span class="inline-flex rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-700">{{ formatReference(row) }}</span>
                    </td>
                    <td class="td-cell">
                      <div class="flex flex-wrap items-center gap-2">
                        <span class="text-slate-600">{{ row.description || '-' }}</span>
                        <span v-if="row.is_grouped && row.serial_count" class="inline-flex items-center gap-1 rounded-full bg-violet-600 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">
                          <i class="fa-solid fa-barcode text-[9px]"></i>{{ row.serial_count }} SN
                        </span>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="row.is_grouped && isGroupExpanded(row)">
                    <td colspan="7" class="bg-violet-50/50 px-4 py-4 sm:px-6">
                      <div class="overflow-hidden rounded-xl border border-violet-200 bg-white shadow-sm">
                        <div class="border-b border-violet-100 bg-gradient-to-r from-violet-600 to-purple-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white">Detail Nomor Seri</div>
                        <table class="min-w-full divide-y divide-violet-100 text-xs">
                          <thead class="bg-violet-50/80">
                            <tr>
                              <th class="px-4 py-2.5 text-left font-bold uppercase text-violet-900">Nomor Seri</th>
                              <th class="px-4 py-2.5 text-right font-bold uppercase text-violet-900">Masuk</th>
                              <th class="px-4 py-2.5 text-right font-bold uppercase text-violet-900">Keluar</th>
                              <th class="px-4 py-2.5 text-left font-bold uppercase text-violet-900">Keterangan</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-violet-50">
                            <tr v-for="line in serialLines(row)" :key="line.id || line.serial_number" class="hover:bg-violet-50/50">
                              <td class="px-4 py-2.5 font-mono text-sm font-semibold text-violet-900">{{ line.serial_number || '-' }}</td>
                              <td class="px-4 py-2.5 text-right tabular-nums text-emerald-700">{{ formatQty(line, 'in') }}</td>
                              <td class="px-4 py-2.5 text-right tabular-nums text-rose-700">{{ formatQty(line, 'out') }}</td>
                              <td class="px-4 py-2.5 text-slate-600">{{ line.description || '-' }}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
          <div v-if="filteredCards.length" class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 sm:px-5">
            <p class="text-sm text-slate-600">Halaman {{ page }} / {{ totalPages }}</p>
            <div class="flex gap-2">
              <button type="button" class="pager-btn" :disabled="page === 1" @click="prevPage"><i class="fa-solid fa-chevron-left"></i></button>
              <button type="button" class="pager-btn" :disabled="page === totalPages" @click="nextPage"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
          </div>
        </div>
      </template>
    </InventoryReportPage>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InventoryReportPage from '@/Components/Inventory/InventoryReportPage.vue';
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import { useInventoryCardSerialRows } from '@/composables/useInventoryCardSerialRows';

const { isGroupExpanded, toggleGroup, serialLines } = useInventoryCardSerialRows();
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

function formatReference(card) {
  if (!card.reference_type) return '-';
  let ref = card.reference_type;
  if (card.reference_type === 'good_receive' && card.reference_number) {
    ref += ' #' + card.reference_number;
  } else if (card.reference_type === 'warehouse_transfer' && card.transfer_number) {
    ref += ' #' + card.transfer_number;
  } else if (card.reference_type === 'delivery_order' && card.do_number) {
    ref += ' #' + card.do_number;
  } else if (card.reference_id) {
    ref += ' #' + card.reference_id;
  }
  return ref;
}

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

const serialGroupCount = computed(() => filteredCards.value.filter((row) => row.is_grouped).length);

const periodLabel = computed(() => {
  if (fromDate.value && toDate.value) return `${fromDate.value} s/d ${toDate.value}`;
  if (fromDate.value) return `Dari ${fromDate.value}`;
  if (toDate.value) return `Sampai ${toDate.value}`;
  return 'Semua periode';
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
.field label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500; }
.field-input { @apply w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100; }
.th-cell { @apply px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider sm:px-5; }
.td-cell { @apply whitespace-nowrap px-4 py-3.5 text-sm text-slate-700 sm:px-5; }
.alert-box { @apply rounded-2xl border px-5 py-4 text-center text-sm font-semibold; }
.alert-info { @apply border-indigo-200 bg-indigo-50 text-indigo-900; }
.alert-warn { @apply border-amber-200 bg-amber-50 text-amber-900; }
.alert-error { @apply border-rose-200 bg-rose-50 text-rose-900; }
.btn-primary { @apply inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60; }
.pager-btn { @apply inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40; }
:deep(.multiselect) {
  min-height: 42px;
  border: 1px solid #cbd5e1;
  border-radius: 0.75rem;
}

:deep(.multiselect:focus-within) {
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
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