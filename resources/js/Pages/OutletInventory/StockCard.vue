<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6">Kartu Stok Outlet (Stock Card)</h1>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input v-model="search" type="text" placeholder="Cari barang, outlet, referensi, keterangan..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />
        <div class="flex items-center gap-2">
          <label class="text-sm">Outlet</label>
          <select v-model="selectedOutlet" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500" :disabled="!outletSelectable">
            <option value="">Semua Outlet</option>
            <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Warehouse Outlet</label>
          <select v-model="selectedWarehouseOutlet" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Warehouse</option>
            <option v-for="w in warehouse_outlets" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Barang</label>
          <select v-model="selectedItem" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="" disabled>Pilih Barang</option>
            <option v-for="i in items" :key="i.id" :value="i.name">{{ i.name }}</option>
          </select>
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
      <div v-if="!selectedItem" class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 p-4 rounded my-8 text-center font-semibold">
        Silakan pilih barang terlebih dahulu untuk melihat kartu stok.
      </div>
      <template v-else>
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50 sticky top-0 z-10">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse Outlet</th>
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
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.outlet_name }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.warehouse_outlet_name || '-' }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ formatQty(row, 'in') }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ formatQty(row, 'out') }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ formatSaldoQty(row) }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    {{ row.reference_type ? row.reference_type + (row.reference_id ? ' #' + row.reference_id : '') : '-' }}
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
            <button @click="prevPage" :disabled="pageNum === 1" class="px-3 py-1 rounded border text-sm" :class="pageNum === 1 ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&lt;</button>
            <span class="px-2">Halaman {{ pageNum }} / {{ totalPages }}</span>
            <button @click="nextPage" :disabled="pageNum === totalPages" class="px-3 py-1 rounded border text-sm" :class="pageNum === totalPages ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&gt;</button>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
const props = defineProps({
  cards: Array,
  outlets: Array,
  items: Array,
  warehouse_outlets: Array
});
const page = usePage();
const userOutletId = computed(() => page.props.auth?.user?.id_outlet || '');
const search = ref('');
const perPage = ref(25);
const pageNum = ref(1);
const selectedOutlet = ref('');
const selectedItem = ref('');
const selectedWarehouseOutlet = ref('');
const loadingReload = ref(false)
const fromDate = ref('');
const toDate = ref('');

// Validasi: hanya superadmin (id_outlet=1) yang bisa pilih outlet
const outletSelectable = computed(() => String(userOutletId.value) === '1');

// Set default outlet jika bukan superadmin
onMounted(() => {
  if (!outletSelectable.value && userOutletId.value) {
    selectedOutlet.value = String(userOutletId.value);
  }
});

const filteredCards = computed(() => {
  let data = props.cards;
  if (selectedOutlet.value) {
    data = data.filter(row => String(row.outlet_name) === String(props.outlets.find(o => o.id == selectedOutlet.value)?.name));
  }
  if (selectedWarehouseOutlet.value) {
    data = data.filter(row => String(row.warehouse_outlet_id) === String(selectedWarehouseOutlet.value));
  }
  if (selectedItem.value) {
    data = data.filter(row => row.item_name === selectedItem.value);
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
    (row.outlet_name && row.outlet_name.toLowerCase().includes(s)) ||
    (row.reference_type && row.reference_type.toLowerCase().includes(s)) ||
    (row.description && row.description.toLowerCase().includes(s))
  );
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
watch([perPage, search], () => { pageNum.value = 1; });

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
function reloadData() {
  loadingReload.value = true
  window.location.reload()
}
</script> 