<template>
  <AppLayout>
    <div class="w-full mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-6 flex items-center gap-2">
        <i class="fas fa-university text-blue-600"></i>
        Rekap Transaksi Bank per Outlet
      </h1>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow p-5 mb-6">
        <div class="flex items-center gap-2 mb-4">
          <i class="fas fa-filter text-blue-500"></i>
          <span class="font-semibold text-gray-700">Filter</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal Dari</label>
            <input v-model="filters.date_from" type="date" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal Sampai</label>
            <input v-model="filters.date_to" type="date" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
            <select v-model="filters.kode_outlet" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2 text-sm">
              <option value="">Semua Outlet</option>
              <option v-for="o in outlets" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Bank</label>
            <select v-model="filters.payment_code" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2 text-sm">
              <option value="">Semua Bank</option>
              <option v-for="b in banks" :key="b.value" :value="b.value">{{ b.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Payment Type</label>
            <select v-model="filters.payment_type" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2 text-sm">
              <option value="">Semua Payment Type</option>
              <option v-for="pt in paymentTypes" :key="pt" :value="pt">{{ pt }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Cari</label>
            <input v-model="filters.search" type="text" placeholder="No bill, approval code..." class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2 text-sm" @keyup.enter="loadData" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Per Halaman</label>
            <select v-model="filters.per_page" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2 text-sm">
              <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
        </div>
        <div class="flex flex-wrap gap-2 mt-4">
          <button @click="loadData" :disabled="loading" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 disabled:opacity-50 text-sm">
            <i v-if="loading" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-sync-alt mr-2"></i>
            Muat Data
          </button>
          <button @click="resetFilters" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-600 rounded-lg font-semibold hover:bg-gray-50 text-sm">
            <i class="fas fa-rotate-left mr-2"></i>
            Reset
          </button>
          <a v-if="dataLoaded" :href="exportUrl" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 text-sm">
            <i class="fas fa-file-excel mr-2"></i>
            Export Excel
          </a>
        </div>
      </div>

      <!-- Summary -->
      <div v-if="dataLoaded && summary" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-4 text-center">
          <p class="text-xs font-medium text-gray-500 mb-1">Total Transaksi</p>
          <p class="text-xl font-bold text-blue-700">{{ summary.total_transaksi }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 text-center">
          <p class="text-xs font-medium text-gray-500 mb-1">Grand Total</p>
          <p class="text-lg font-bold text-gray-800">{{ formatRupiah(summary.sum_grand_total) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 text-center">
          <p class="text-xs font-medium text-gray-500 mb-1">Total Discount</p>
          <p class="text-lg font-bold text-red-600">{{ formatRupiah(summary.sum_discount) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 text-center">
          <p class="text-xs font-medium text-gray-500 mb-1">Total DPP</p>
          <p class="text-lg font-bold text-gray-800">{{ formatRupiah(summary.sum_dpp) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 text-center">
          <p class="text-xs font-medium text-gray-500 mb-1">Total PPN/PB1</p>
          <p class="text-lg font-bold text-gray-800">{{ formatRupiah(summary.sum_pb1) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 text-center">
          <p class="text-xs font-medium text-gray-500 mb-1">Total Service</p>
          <p class="text-lg font-bold text-gray-800">{{ formatRupiah(summary.sum_service) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 text-center">
          <p class="text-xs font-medium text-gray-500 mb-1">Total Nilai Gesek</p>
          <p class="text-lg font-bold text-green-700">{{ formatRupiah(summary.sum_nilai_gesek) }}</p>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full border border-gray-300">
            <thead>
              <tr class="bg-blue-600 text-white">
                <th class="px-3 py-3 border border-blue-500 text-center text-xs font-semibold uppercase">No</th>
                <th v-for="col in sortableColumns" :key="col.key"
                    class="px-3 py-3 border border-blue-500 text-xs font-semibold uppercase cursor-pointer select-none hover:bg-blue-700 transition"
                    :class="col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'"
                    @click="toggleSort(col.key)"
                >
                  <span class="inline-flex items-center gap-1" :class="col.align === 'right' ? 'justify-end' : ''">
                    {{ col.label }}
                    <i v-if="filters.sort_by === col.key" class="fas text-[10px]" :class="filters.sort_dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down'"></i>
                    <i v-else class="fas fa-sort text-[10px] opacity-40"></i>
                  </span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!data.length">
                <td colspan="13" class="text-center py-12 text-gray-400">
                  <div class="flex flex-col items-center gap-3">
                    <i class="fas fa-credit-card text-4xl"></i>
                    <p class="font-medium" v-if="!dataLoaded">Pilih filter dan klik "Muat Data" untuk melihat data.</p>
                    <p class="font-medium" v-else>Tidak ada data transaksi bank.</p>
                  </div>
                </td>
              </tr>
              <tr v-for="(row, idx) in data" :key="idx" class="hover:bg-blue-50/50 transition">
                <td class="px-3 py-2.5 border border-gray-200 text-center text-sm text-gray-500">{{ rowNumber(idx) }}</td>
                <td class="px-3 py-2.5 border border-gray-200 text-sm text-gray-700">{{ formatDate(row.tanggal) }}</td>
                <td class="px-3 py-2.5 border border-gray-200 text-sm font-medium text-gray-900">{{ row.paid_number || '-' }}</td>
                <td class="px-3 py-2.5 border border-gray-200 text-sm text-gray-700">{{ row.bank_name || '-' }}</td>
                <td class="px-3 py-2.5 border border-gray-200 text-center text-sm font-mono text-gray-700">{{ row.card_first4 || '-' }}</td>
                <td class="px-3 py-2.5 border border-gray-200 text-center text-sm font-mono text-gray-700">{{ row.card_last4 || '-' }}</td>
                <td class="px-3 py-2.5 border border-gray-200 text-center text-sm font-mono text-blue-700 font-semibold">{{ row.approval_code || '-' }}</td>
                <td class="px-3 py-2.5 border border-gray-200 text-right text-sm font-medium text-gray-900">{{ formatRupiah(row.grand_total) }}</td>
                <td class="px-3 py-2.5 border border-gray-200 text-right text-sm text-red-600">{{ formatRupiah(row.total_discount) }}</td>
                <td class="px-3 py-2.5 border border-gray-200 text-right text-sm text-gray-700">{{ formatRupiah(row.dpp) }}</td>
                <td class="px-3 py-2.5 border border-gray-200 text-right text-sm text-gray-700">{{ formatRupiah(row.pb1) }}</td>
                <td class="px-3 py-2.5 border border-gray-200 text-right text-sm text-gray-700">{{ formatRupiah(row.service) }}</td>
                <td class="px-3 py-2.5 border border-gray-200 text-right text-sm font-semibold text-green-700">{{ formatRupiah(row.nilai_gesek) }}</td>
              </tr>
            </tbody>
            <tfoot v-if="data.length && summary">
              <tr class="bg-gray-100 font-semibold">
                <td colspan="7" class="px-3 py-3 text-right text-sm">Total</td>
                <td class="px-3 py-3 border border-gray-200 text-right text-sm text-gray-900">{{ formatRupiah(summary.sum_grand_total) }}</td>
                <td class="px-3 py-3 border border-gray-200 text-right text-sm text-red-600">{{ formatRupiah(summary.sum_discount) }}</td>
                <td class="px-3 py-3 border border-gray-200 text-right text-sm text-gray-900">{{ formatRupiah(summary.sum_dpp) }}</td>
                <td class="px-3 py-3 border border-gray-200 text-right text-sm text-gray-900">{{ formatRupiah(summary.sum_pb1) }}</td>
                <td class="px-3 py-3 border border-gray-200 text-right text-sm text-gray-900">{{ formatRupiah(summary.sum_service) }}</td>
                <td class="px-3 py-3 border border-gray-200 text-right text-sm text-green-700">{{ formatRupiah(summary.sum_nilai_gesek) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="data.length" class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 px-5 py-4 border-t border-gray-200 bg-gray-50/70">
          <span class="text-sm text-gray-600">
            Menampilkan {{ ((currentPage - 1) * perPage) + 1 }} - {{ Math.min(currentPage * perPage, total) }} dari {{ total }} data
          </span>
          <div class="flex items-center gap-1">
            <button
              v-for="p in paginationPages"
              :key="p"
              @click="goToPage(p)"
              :disabled="p === currentPage"
              class="px-3 py-1.5 rounded-lg text-sm font-medium transition"
              :class="p === currentPage ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'"
            >
              {{ p }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
  data: { type: Array, default: () => [] },
  outlets: { type: Array, default: () => [] },
  banks: { type: Array, default: () => [] },
  paymentTypes: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  summary: { type: Object, default: null },
  dataLoaded: { type: Boolean, default: false },
  total: { type: Number, default: 0 },
  current_page: { type: Number, default: 1 },
  per_page: { type: Number, default: 25 },
  last_page: { type: Number, default: 1 },
});

const loading = ref(false);
const filters = ref({
  kode_outlet: props.filters.kode_outlet || '',
  payment_code: props.filters.payment_code || '',
  payment_type: props.filters.payment_type || '',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  search: props.filters.search || '',
  per_page: props.filters.per_page || props.per_page || 25,
  sort_by: props.filters.sort_by || 'tanggal',
  sort_dir: props.filters.sort_dir || 'desc',
});

const sortableColumns = [
  { key: 'tanggal', label: 'Tanggal', align: 'left' },
  { key: 'paid_number', label: 'No. Bill', align: 'left' },
  { key: 'payment_type', label: 'Payment Type', align: 'left' },
  { key: 'card_first4', label: 'Card First', align: 'center' },
  { key: 'card_last4', label: 'Card Last', align: 'center' },
  { key: 'approval_code', label: 'Approval Code', align: 'center' },
  { key: 'grand_total', label: 'Grand Total', align: 'right' },
  { key: 'total_discount', label: 'Discount', align: 'right' },
  { key: 'dpp', label: 'DPP', align: 'right' },
  { key: 'pb1', label: 'PPN/PB1', align: 'right' },
  { key: 'service', label: 'Service', align: 'right' },
  { key: 'nilai_gesek', label: 'Nilai Gesek', align: 'right' },
];

function toggleSort(key) {
  if (filters.value.sort_by === key) {
    filters.value.sort_dir = filters.value.sort_dir === 'asc' ? 'desc' : 'asc';
  } else {
    filters.value.sort_by = key;
    filters.value.sort_dir = 'asc';
  }
  if (dataLoaded.value) loadData();
}

const currentPage = computed(() => props.current_page);
const perPage = computed(() => props.per_page);
const total = computed(() => props.total);
const lastPage = computed(() => props.last_page);
const dataLoaded = computed(() => props.dataLoaded);

const paginationPages = computed(() => {
  const pages = [];
  const lp = lastPage.value;
  const cp = currentPage.value;
  const range = 2;
  for (let i = Math.max(1, cp - range); i <= Math.min(lp, cp + range); i++) {
    pages.push(i);
  }
  if (pages[0] > 1) pages.unshift(1);
  if (pages[pages.length - 1] < lp) pages.push(lp);
  return [...new Set(pages)].sort((a, b) => a - b);
});

const exportUrl = computed(() => {
  const params = new URLSearchParams();
  if (filters.value.kode_outlet) params.append('kode_outlet', filters.value.kode_outlet);
  if (filters.value.payment_code) params.append('payment_code', filters.value.payment_code);
  if (filters.value.payment_type) params.append('payment_type', filters.value.payment_type);
  if (filters.value.date_from) params.append('date_from', filters.value.date_from);
  if (filters.value.date_to) params.append('date_to', filters.value.date_to);
  if (filters.value.search) params.append('search', filters.value.search);
  const qs = params.toString();
  return `/report-bank-transaction/export${qs ? '?' + qs : ''}`;
});

function loadData() {
  loading.value = true;
  router.get(route('report.bank-transaction'), {
    ...filters.value,
    load_data: 1,
    page: 1,
  }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => { loading.value = false; },
  });
}

function goToPage(page) {
  loading.value = true;
  router.get(route('report.bank-transaction'), {
    ...filters.value,
    load_data: 1,
    page,
  }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => { loading.value = false; },
  });
}

function resetFilters() {
  filters.value = { kode_outlet: '', payment_code: '', payment_type: '', date_from: '', date_to: '', search: '', per_page: 25, sort_by: 'tanggal', sort_dir: 'desc' };
  router.get(route('report.bank-transaction'), {}, { preserveState: false });
}

function rowNumber(idx) {
  return ((currentPage.value - 1) * perPage.value) + idx + 1;
}

function formatDate(val) {
  if (!val) return '-';
  return new Date(val).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatRupiah(val) {
  if (val == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(val));
}
</script>
