<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  priceChanges: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  loaded: { type: Boolean, default: false },
});

function defaultMonthRange() {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');
  return {
    from: `${y}-${m}-01`,
    to: `${y}-${m}-${d}`,
  };
}

const defaults = defaultMonthRange();
const search = ref('');
const dateFrom = ref(props.filters?.date_from || defaults.from);
const dateTo = ref(props.filters?.date_to || defaults.to);

const filteredData = computed(() => {
  if (!search.value) return props.priceChanges || [];
  return (props.priceChanges || []).filter((row) =>
    row.item_name?.toLowerCase().includes(search.value.toLowerCase())
  );
});

function loadData() {
  router.get(
    route('po_price_change_report.index'),
    {
      load: 1,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
    },
    { preserveState: true, preserveScroll: true }
  );
}

function formatDate(val) {
  if (!val) return '-';
  return new Date(val).toLocaleDateString('id-ID');
}
</script>

<template>
  <AppLayout>
    <div class="p-8">
      <h1 class="text-2xl font-bold mb-4 text-blue-700">Laporan Perubahan Harga PO</h1>

      <div class="mb-4 flex flex-col md:flex-row gap-2 items-start md:items-end flex-wrap">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal From</label>
          <input
            v-model="dateFrom"
            type="date"
            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal To</label>
          <input
            v-model="dateTo"
            type="date"
            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div class="w-full md:w-auto md:flex-1 min-w-[200px]">
          <label class="block text-sm font-medium text-gray-700 mb-1">Cari Barang</label>
          <input
            v-model="search"
            type="text"
            placeholder="Cari nama barang..."
            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 w-full"
          />
        </div>
        <button
          type="button"
          @click="loadData"
          class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold shadow"
        >
          Load Data
        </button>
      </div>

      <p class="text-xs text-gray-500 mb-4">
        Filter tanggal berlaku untuk <strong>PO terbaru</strong> (harga baru). Harga awal tetap diambil dari PO sebelumnya (boleh di luar rentang).
      </p>

      <div v-if="!loaded" class="bg-white rounded-xl shadow-lg p-10 text-center text-gray-400">
        Pilih tanggal (opsional) lalu klik <strong>Load Data</strong>.
      </div>

      <div v-else class="bg-white rounded-xl shadow-lg p-8 text-gray-600 overflow-x-auto">
        <table class="min-w-full border text-xs md:text-sm">
          <thead class="bg-blue-50">
            <tr>
              <th class="border px-2 py-1">No</th>
              <th class="border px-2 py-1">Nama Barang</th>
              <th class="border px-2 py-1">Satuan Large</th>
              <th class="border px-2 py-1">Supplier Awal</th>
              <th class="border px-2 py-1">Harga Awal</th>
              <th class="border px-2 py-1">Tgl PO Awal</th>
              <th class="border px-2 py-1">Supplier Baru</th>
              <th class="border px-2 py-1">Harga Baru</th>
              <th class="border px-2 py-1">Tgl PO Baru</th>
              <th class="border px-2 py-1">Presentase kenaikan/penurunan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!filteredData.length">
              <td colspan="10" class="text-center py-8 text-gray-400">Tidak ada data perubahan harga PO.</td>
            </tr>
            <tr v-for="(row, i) in filteredData" :key="row.item_name + i">
              <td class="border px-2 py-1 text-center">{{ i + 1 }}</td>
              <td class="border px-2 py-1">{{ row.item_name }}</td>
              <td class="border px-2 py-1">{{ row.large_unit_name || '-' }}</td>
              <td class="border px-2 py-1">{{ row.supplier_awal }}</td>
              <td class="border px-2 py-1 text-right">{{ Number(row.harga_awal).toLocaleString('id-ID') }}</td>
              <td class="border px-2 py-1 text-center whitespace-nowrap">{{ formatDate(row.po_date_awal) }}</td>
              <td class="border px-2 py-1">{{ row.supplier_baru }}</td>
              <td class="border px-2 py-1 text-right">{{ Number(row.harga_baru).toLocaleString('id-ID') }}</td>
              <td class="border px-2 py-1 text-center whitespace-nowrap">{{ formatDate(row.po_date_baru) }}</td>
              <td class="border px-2 py-1 text-center">
                <span :class="row.persen > 0 ? 'text-red-600 font-bold' : row.persen < 0 ? 'text-green-600 font-bold' : ''">
                  {{ row.persen > 0 ? '+' : '' }}{{ row.persen }}%
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
