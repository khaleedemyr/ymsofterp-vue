<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-scale-unbalanced"></i>
          Report Selisih Harga/Discount PO vs Contra Bon
        </h1>
      </div>

      <p class="text-sm text-gray-600 mb-4">
        Menampilkan baris Contra Bon (sumber PO Foods) yang harga/unit atau discount-nya berbeda dari item PO.
      </p>

      <div class="flex flex-wrap gap-3 mb-4 items-end">
        <div class="flex-1 min-w-64">
          <label class="block text-xs text-gray-500 mb-1">Cari</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="CB / PO / Supplier / Item..."
            class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
          />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Status CB</label>
          <select v-model="filters.status" class="px-4 py-2 rounded-xl border border-blue-200 shadow">
            <option value="">Semua</option>
            <option value="draft">Draft</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Supplier</label>
          <select v-model="filters.supplier_id" class="px-4 py-2 rounded-xl border border-blue-200 shadow min-w-[180px]">
            <option value="">Semua Supplier</option>
            <option v-for="s in suppliers" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Dari</label>
          <input type="date" v-model="filters.date_from" class="px-2 py-2 rounded-xl border border-blue-200 shadow" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Sampai</label>
          <input type="date" v-model="filters.date_to" class="px-2 py-2 rounded-xl border border-blue-200 shadow" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Jenis Selisih</label>
          <select v-model="filters.diff_type" class="px-4 py-2 rounded-xl border border-blue-200 shadow">
            <option value="any">Harga atau Discount</option>
            <option value="price">Hanya Harga/Unit</option>
            <option value="discount">Hanya Discount</option>
          </select>
        </div>
        <label class="flex items-center gap-2 px-2 py-2 text-sm text-gray-700">
          <input type="checkbox" v-model="filters.only_diff" class="rounded border-gray-300" />
          Hanya yang beda
        </label>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Per halaman</label>
          <select v-model="filters.per_page" class="px-4 py-2 rounded-xl border border-blue-200 shadow">
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>
        <button
          @click="loadData"
          :disabled="isLoading"
          class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-6 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2 disabled:opacity-50"
        >
          <i v-if="!isLoading" class="fa fa-download"></i>
          <i v-else class="fa fa-spinner fa-spin"></i>
          {{ isLoading ? 'Memuat...' : 'Load Data' }}
        </button>
      </div>

      <template v-if="isLoading && !isDataLoaded">
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-8 text-center">
          <i class="fa fa-spinner fa-spin text-blue-500 text-3xl mb-3"></i>
          <p class="text-gray-700 font-semibold">Memuat data...</p>
        </div>
      </template>

      <template v-else-if="!isDataLoaded">
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-8 text-center">
          <i class="fa fa-info-circle text-blue-500 text-4xl mb-4"></i>
          <p class="text-gray-700 text-lg font-semibold mb-2">Data Belum Dimuat</p>
          <p class="text-gray-600">Atur filter lalu klik Load Data.</p>
        </div>
      </template>

      <template v-else>
        <div v-if="summary" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
          <div class="bg-white rounded-xl shadow p-4 border">
            <div class="text-xs text-gray-500">Baris dibanding</div>
            <div class="text-xl font-bold text-gray-800">{{ summary.total_compared_lines }}</div>
          </div>
          <div class="bg-white rounded-xl shadow p-4 border">
            <div class="text-xs text-gray-500">Ada selisih</div>
            <div class="text-xl font-bold text-amber-600">{{ summary.any_diff_count }}</div>
          </div>
          <div class="bg-white rounded-xl shadow p-4 border">
            <div class="text-xs text-gray-500">Selisih harga/unit</div>
            <div class="text-xl font-bold text-red-600">{{ summary.price_diff_count }}</div>
          </div>
          <div class="bg-white rounded-xl shadow p-4 border">
            <div class="text-xs text-gray-500">Selisih discount</div>
            <div class="text-xl font-bold text-purple-600">{{ summary.discount_diff_count }}</div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase">Tanggal CB</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase">Contra Bon</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase">PO</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase">Supplier</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase">Item</th>
                <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase">Qty CB</th>
                <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase">Harga PO</th>
                <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase">Harga CB</th>
                <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase">Δ Harga</th>
                <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase">Disc% PO</th>
                <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase">Disc% CB</th>
                <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase">Disc Rp PO</th>
                <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase">Disc Rp CB</th>
                <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase">Expected Line</th>
                <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase">Total CB</th>
                <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase">Δ Line</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase">Flag</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="!safeRows.data.length">
                <td colspan="17" class="px-4 py-8 text-center text-gray-500">
                  Tidak ada data sesuai filter.
                </td>
              </tr>
              <tr
                v-for="row in safeRows.data"
                :key="row.cb_item_id || (row.cb_number + '-' + row.item_name + '-' + row.po_price)"
                class="hover:bg-gray-50"
              >
                <td class="px-3 py-2 whitespace-nowrap">{{ formatDate(row.cb_date) }}</td>
                <td class="px-3 py-2 whitespace-nowrap">
                  <a
                    v-if="row.contra_bon_id"
                    :href="`/contra-bons/${row.contra_bon_id}`"
                    class="text-blue-600 hover:underline font-medium"
                    target="_blank"
                  >{{ row.cb_number }}</a>
                  <span v-else>{{ row.cb_number }}</span>
                  <div class="text-xs text-gray-400">{{ row.cb_status }}</div>
                </td>
                <td class="px-3 py-2 whitespace-nowrap">{{ row.po_number || '-' }}</td>
                <td class="px-3 py-2">{{ row.supplier_name || '-' }}</td>
                <td class="px-3 py-2">
                  <div class="font-medium text-gray-800">{{ row.item_name || '-' }}</div>
                  <div class="text-xs text-gray-400">{{ row.item_sku || '' }} {{ row.unit_name ? '· ' + row.unit_name : '' }}</div>
                </td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ formatNumber(row.cb_qty) }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ formatCurrency(row.po_price) }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ formatCurrency(row.cb_price) }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap" :class="diffClass(row.price_diff)">
                  {{ formatSignedCurrency(row.price_diff) }}
                </td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ formatNumber(row.po_discount_percent) }}%</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ formatNumber(row.cb_discount_percent) }}%</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ formatCurrency(row.po_discount_amount) }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ formatCurrency(row.cb_discount_amount) }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ formatCurrency(row.po_expected_line_total) }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ formatCurrency(row.cb_line_total) }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap" :class="diffClass(row.line_total_diff)">
                  {{ formatSignedCurrency(row.line_total_diff) }}
                </td>
                <td class="px-3 py-2 whitespace-nowrap">
                  <span
                    v-for="flag in row.diff_flags"
                    :key="flag"
                    class="inline-block mr-1 mb-1 px-2 py-0.5 rounded text-xs font-semibold"
                    :class="flag === 'price' ? 'bg-red-100 text-red-700' : 'bg-purple-100 text-purple-700'"
                  >{{ flag }}</span>
                  <span v-if="!row.diff_flags?.length" class="text-gray-400 text-xs">sama</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="safeRows.links?.length" class="flex flex-wrap gap-2 mt-4">
          <button
            v-for="(link, idx) in safeRows.links"
            :key="idx"
            v-html="link.label"
            :disabled="!link.url || isLoading"
            @click="goPage(link.url)"
            class="px-3 py-1 rounded-lg border text-sm font-semibold"
            :class="[
              link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
              !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
            ]"
          />
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  rows: Object,
  summary: Object,
  suppliers: {
    type: Array,
    default: () => [],
  },
  filters: Object,
  dataLoaded: {
    type: Boolean,
    default: false,
  },
});

const getTodayDate = () => new Date().toISOString().split('T')[0];

const filters = ref({
  search: props.filters?.search || '',
  status: props.filters?.status || '',
  supplier_id: props.filters?.supplier_id ? String(props.filters.supplier_id) : '',
  date_from: props.filters?.date_from || getTodayDate(),
  date_to: props.filters?.date_to || getTodayDate(),
  diff_type: props.filters?.diff_type || 'any',
  only_diff: props.filters?.only_diff !== false && props.filters?.only_diff !== 0 && props.filters?.only_diff !== '0',
  per_page: props.filters?.per_page || 25,
});

const isDataLoaded = ref(!!props.dataLoaded);
const isLoading = ref(false);

watch(
  () => props.dataLoaded,
  (v) => { isDataLoaded.value = !!v; },
  { immediate: true }
);

const safeRows = computed(() => {
  if (!props.rows || typeof props.rows !== 'object') {
    return { data: [], links: [], total: 0 };
  }
  return {
    ...props.rows,
    data: Array.isArray(props.rows.data) ? props.rows.data : [],
    links: Array.isArray(props.rows.links) ? props.rows.links : [],
  };
});

function queryParams() {
  return {
    load_data: 1,
    search: filters.value.search || undefined,
    status: filters.value.status || undefined,
    supplier_id: filters.value.supplier_id || undefined,
    date_from: filters.value.date_from || undefined,
    date_to: filters.value.date_to || undefined,
    diff_type: filters.value.diff_type || 'any',
    only_diff: filters.value.only_diff ? 1 : 0,
    per_page: filters.value.per_page || 25,
  };
}

function loadData() {
  isLoading.value = true;
  router.get(route('contra-bon-po-variance-report.index'), queryParams(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    onFinish: () => { isLoading.value = false; },
  });
}

function goPage(url) {
  if (!url) return;
  isLoading.value = true;
  router.get(url, {}, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => { isLoading.value = false; },
  });
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

function formatNumber(val) {
  const n = Number(val || 0);
  return n.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatCurrency(val) {
  const n = Number(val || 0);
  return n.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatSignedCurrency(val) {
  const n = Number(val || 0);
  const abs = Math.abs(n).toLocaleString('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 2 });
  if (Math.abs(n) < 0.0001) return abs;
  return (n > 0 ? '+' : '-') + abs;
}

function diffClass(val) {
  const n = Number(val || 0);
  if (Math.abs(n) < 0.0001) return 'text-gray-500';
  return n > 0 ? 'text-red-600 font-semibold' : 'text-green-700 font-semibold';
}
</script>
