<template>
  <AppLayout>
    <InventoryReportPage
      eyebrow="Outlet Inventory"
      title="Kartu Stok Outlet"
      subtitle="Riwayat mutasi stok per barang. Transaksi nomor seri dalam satu kedatangan digabung — klik baris bertanda SN untuk melihat detail."
      variant="outlet"
    >
      <template #badges>
        <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold backdrop-blur">
          <i class="fa-solid fa-layer-group mr-1.5 opacity-80"></i>
          Serial grouping aktif
        </span>
      </template>

      <template v-if="selectedItem && filteredCards.length" #stats>
        <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
          <p class="text-[11px] uppercase tracking-wide text-sky-200/80">Barang</p>
          <p class="mt-1 truncate text-sm font-bold">{{ selectedItem?.name || '—' }}</p>
        </div>
        <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
          <p class="text-[11px] uppercase tracking-wide text-sky-200/80">Total Transaksi</p>
          <p class="mt-1 text-lg font-bold tabular-nums">{{ filteredCards.length }}</p>
        </div>
        <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
          <p class="text-[11px] uppercase tracking-wide text-sky-200/80">Grup Serial</p>
          <p class="mt-1 text-lg font-bold tabular-nums">{{ serialGroupCount }}</p>
        </div>
        <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
          <p class="text-[11px] uppercase tracking-wide text-sky-200/80">Periode</p>
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
            <label>Outlet</label>
            <select v-model="selectedOutlet" class="field-input" :disabled="!outletSelectable">
              <option value="">Semua Outlet</option>
              <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div class="field">
            <label>Warehouse Outlet</label>
            <select v-model="selectedWarehouseOutlet" class="field-input">
              <option value="">Semua Warehouse</option>
              <option v-for="w in filteredWarehouseOutlets" :key="w.id" :value="w.id">{{ w.name }}</option>
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
            <button
              type="button"
              class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="loadingReload"
              @click="reloadData"
            >
              <i :class="loadingReload ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-database'"></i>
              {{ loadingReload ? 'Memuat...' : 'Tampilkan Data' }}
            </button>
          </div>
        </div>
      </template>

      <div v-if="props.error" class="alert-box alert-error">{{ props.error }}</div>

      <div v-else-if="!selectedItem" class="alert-box alert-info">
        <i class="fa-solid fa-circle-info mr-2"></i>
        Pilih barang, filter outlet/warehouse, lalu klik <strong>Tampilkan Data</strong>.
      </div>

      <div v-else-if="selectedItem && cards.length === 0" class="alert-box alert-warn">
        <i class="fa-solid fa-triangle-exclamation mr-2"></i>
        Tidak ada data kartu stok. Coba ubah filter atau periode tanggal.
      </div>

      <template v-else-if="selectedItem && cards.length > 0">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:px-5">
            <div class="text-sm text-slate-600">
              Menampilkan <span class="font-bold text-slate-900">{{ startIndex + 1 }}–{{ endIndex }}</span> dari
              <span class="font-bold text-slate-900">{{ filteredCards.length }}</span> transaksi
            </div>
            <div class="text-xs text-slate-500">
              <span class="mr-3"><span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span> Masuk</span>
              <span class="mr-3"><span class="inline-block h-2 w-2 rounded-full bg-rose-500"></span> Keluar</span>
              <span><span class="inline-block h-2 w-2 rounded-full bg-violet-500"></span> Klik baris SN untuk expand</span>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
              <thead class="bg-slate-800 text-white">
                <tr>
                  <th class="th-cell">Tanggal</th>
                  <th class="th-cell">Outlet</th>
                  <th class="th-cell">Warehouse</th>
                  <th class="th-cell text-right">Masuk</th>
                  <th class="th-cell text-right">Keluar</th>
                  <th class="th-cell text-right">Saldo</th>
                  <th class="th-cell">Referensi</th>
                  <th class="th-cell">Keterangan</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <tr v-if="!filteredCards.length">
                  <td colspan="8" class="px-6 py-12 text-center text-slate-400">Tidak ada data kartu stok.</td>
                </tr>
                <template v-for="(row, index) in paginatedCards" :key="row.group_key || row.id || index">
                  <tr
                    :class="[
                      index === paginatedCards.length - 1
                        ? 'bg-amber-50 font-semibold ring-1 ring-inset ring-amber-200/80'
                        : row.is_grouped
                          ? 'cursor-pointer bg-violet-50/30 hover:bg-violet-50/60'
                          : 'hover:bg-slate-50/80',
                      'transition',
                    ]"
                    @click="row.is_grouped ? toggleGroup(row, $event) : null"
                  >
                    <td class="td-cell">
                      <div class="flex items-center gap-2">
                        <span
                          v-if="row.is_grouped"
                          class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-violet-100 text-violet-700"
                        >
                          <i :class="isGroupExpanded(row) ? 'fa-solid fa-chevron-down text-[10px]' : 'fa-solid fa-chevron-right text-[10px]'"></i>
                        </span>
                        <span class="font-medium">{{ row.date ? new Date(row.date).toLocaleDateString('id-ID') : '-' }}</span>
                      </div>
                    </td>
                    <td class="td-cell">{{ row.outlet_name }}</td>
                    <td class="td-cell">{{ row.warehouse_outlet_name || '-' }}</td>
                    <td class="td-cell text-right tabular-nums text-emerald-700">{{ formatQty(row, 'in') }}</td>
                    <td class="td-cell text-right tabular-nums text-rose-700">{{ formatQty(row, 'out') }}</td>
                    <td class="td-cell text-right tabular-nums font-medium text-slate-900">{{ formatSaldoQty(row) }}</td>
                    <td class="td-cell">
                      <span class="inline-flex rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-700">
                        {{ row.reference_type ? row.reference_type + (row.reference_id ? ' #' + row.reference_id : '') : '-' }}
                      </span>
                    </td>
                    <td class="td-cell">
                      <div class="flex flex-wrap items-center gap-2">
                        <span class="text-slate-600">{{ row.description || '-' }}</span>
                        <span
                          v-if="row.is_grouped && row.serial_count"
                          class="inline-flex items-center gap-1 rounded-full bg-violet-600 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white"
                        >
                          <i class="fa-solid fa-barcode text-[9px]"></i>
                          {{ row.serial_count }} SN
                        </span>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="row.is_grouped && isGroupExpanded(row)">
                    <td colspan="8" class="bg-violet-50/50 px-4 py-4 sm:px-6">
                      <div class="overflow-hidden rounded-xl border border-violet-200 bg-white shadow-sm">
                        <div class="border-b border-violet-100 bg-gradient-to-r from-violet-600 to-purple-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white">
                          Detail Nomor Seri
                        </div>
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

          <div v-if="filteredCards.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3 sm:px-5">
            <p class="text-sm text-slate-600">Halaman {{ pageNum }} / {{ totalPages }}</p>
            <div class="flex gap-2">
              <button type="button" class="pager-btn" :disabled="pageNum === 1" @click="prevPage">
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              <button type="button" class="pager-btn" :disabled="pageNum === totalPages" @click="nextPage">
                <i class="fa-solid fa-chevron-right"></i>
              </button>
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
import { ref, computed, watch, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import { useInventoryCardSerialRows } from '@/composables/useInventoryCardSerialRows';

const { isGroupExpanded, toggleGroup, serialLines } = useInventoryCardSerialRows();

const props = defineProps({
  cards: Array,
  outlets: Array,
  items: Array,
  warehouse_outlets: Array,
  saldo_awal: Object,
  error: String,
  user_outlet_id: Number,
});

const page = usePage();
const userOutletId = computed(() => page.props.auth?.user?.id_outlet || '');
const search = ref('');
const perPage = ref(25);
const pageNum = ref(1);
const selectedOutlet = ref('');
const selectedItem = ref('');
const selectedWarehouseOutlet = ref('');
const loadingReload = ref(false);
const fromDate = ref('');
const toDate = ref('');

const outletSelectable = computed(() => String(userOutletId.value) === '1');

const filteredWarehouseOutlets = computed(() => {
  let warehouseOutlets = props.warehouse_outlets;
  if (!outletSelectable.value && userOutletId.value) {
    warehouseOutlets = warehouseOutlets.filter((wo) => String(wo.outlet_id) === String(userOutletId.value));
  }
  if (selectedOutlet.value) {
    warehouseOutlets = warehouseOutlets.filter((wo) => String(wo.outlet_id) === String(selectedOutlet.value));
  }
  return warehouseOutlets;
});

watch(selectedOutlet, () => {
  selectedWarehouseOutlet.value = '';
});

onMounted(() => {
  if (!outletSelectable.value && userOutletId.value) {
    selectedOutlet.value = String(userOutletId.value);
  }
});

const filteredCards = computed(() => {
  let data = props.cards;
  if (selectedOutlet.value) {
    data = data.filter(
      (row) => String(row.outlet_name) === String(props.outlets.find((o) => o.id == selectedOutlet.value)?.name)
    );
  }
  if (selectedWarehouseOutlet.value) {
    data = data.filter((row) => String(row.warehouse_outlet_id) === String(selectedWarehouseOutlet.value));
  }
  if (selectedItem.value) {
    data = data.filter((row) => row.item_name === selectedItem.value.name);
  }
  if (fromDate.value) {
    data = data.filter((row) => new Date(row.date) >= new Date(fromDate.value));
  }
  if (toDate.value) {
    data = data.filter((row) => new Date(row.date) <= new Date(toDate.value));
  }
  if (!search.value) return data;
  const s = search.value.toLowerCase();
  return data.filter(
    (row) =>
      (row.item_name && row.item_name.toLowerCase().includes(s)) ||
      (row.outlet_name && row.outlet_name.toLowerCase().includes(s)) ||
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
const startIndex = computed(() => (pageNum.value - 1) * perPage.value);
const endIndex = computed(() => Math.min(startIndex.value + perPage.value, filteredCards.value.length));
const paginatedCards = computed(() => filteredCards.value.slice(startIndex.value, endIndex.value));

function prevPage() {
  if (pageNum.value > 1) pageNum.value--;
}
function nextPage() {
  if (pageNum.value < totalPages.value) pageNum.value++;
}
watch([perPage, search], () => {
  pageNum.value = 1;
});

function formatNumber(val) {
  if (val == null) return 0;
  if (Number(val) % 1 === 0) return Number(val);
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatQty(row, type = null) {
  if (type === 'in') {
    return `${formatNumber(row.in_qty_small)} ${row.small_unit_name || ''} | ${formatNumber(row.in_qty_medium)} ${row.medium_unit_name || ''} | ${formatNumber(row.in_qty_large)} ${row.large_unit_name || ''}`;
  }
  if (type === 'out') {
    return `${formatNumber(row.out_qty_small)} ${row.small_unit_name || ''} | ${formatNumber(row.out_qty_medium)} ${row.medium_unit_name || ''} | ${formatNumber(row.out_qty_large)} ${row.large_unit_name || ''}`;
  }
  return '-';
}

function formatSaldoQty(row) {
  return `${formatNumber(row.saldo_qty_small)} ${row.small_unit_name || ''} | ${formatNumber(row.saldo_qty_medium)} ${row.medium_unit_name || ''} | ${formatNumber(row.saldo_qty_large)} ${row.large_unit_name || ''}`;
}

function reloadData() {
  if (!selectedItem.value) {
    alert('Silakan pilih barang terlebih dahulu!');
    return;
  }
  loadingReload.value = true;
  const params = {
    item_id: selectedItem.value?.id || '',
    outlet_id: selectedOutlet.value || '',
    warehouse_outlet_id: selectedWarehouseOutlet.value || '',
    from: fromDate.value || '',
    to: toDate.value || '',
  };
  Object.keys(params).forEach((key) => {
    if (!params[key]) delete params[key];
  });
  router.get('/outlet-inventory/stock-card', params, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loadingReload.value = false;
    },
    onError: () => {
      loadingReload.value = false;
    },
  });
}
</script>

<style scoped>
.field label {
  @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500;
}
.field-input {
  @apply w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100;
}
.th-cell {
  @apply px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider sm:px-5;
}
.td-cell {
  @apply whitespace-nowrap px-4 py-3.5 text-sm text-slate-700 sm:px-5;
}
.alert-box {
  @apply rounded-2xl border px-5 py-4 text-center text-sm font-semibold;
}
.alert-info {
  @apply border-sky-200 bg-sky-50 text-sky-900;
}
.alert-warn {
  @apply border-amber-200 bg-amber-50 text-amber-900;
}
.alert-error {
  @apply border-rose-200 bg-rose-50 text-rose-900;
}
.pager-btn {
  @apply inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40;
}
:deep(.multiselect) {
  min-height: 42px;
  border: 1px solid #cbd5e1;
  border-radius: 0.75rem;
}
:deep(.multiselect:focus-within) {
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
}
</style>
