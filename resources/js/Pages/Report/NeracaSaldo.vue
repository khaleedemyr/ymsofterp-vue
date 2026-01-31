<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 via-blue-50/30 to-gray-50 py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2 flex items-center gap-3">
              <div class="p-2 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg">
                <i class="fas fa-balance-scale text-white text-xl"></i>
              </div>
              <span>Neraca Saldo</span>
              <span class="text-lg font-normal text-gray-500">(Trial Balance)</span>
            </h1>
            <p class="text-sm text-gray-600 ml-14">Ringkasan saldo semua akun pada tanggal tertentu</p>
          </div>
        </div>
      </div>

      <!-- Filter Card - Modern Design -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mb-6 backdrop-blur-sm">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <i class="fas fa-filter text-purple-600"></i>
            Filter Laporan
          </h2>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fas fa-calendar-alt text-purple-500 text-xs"></i>
              Tanggal
            </label>
            <input 
              v-model="dateAs" 
              type="date" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200 bg-white hover:border-gray-300"
            />
          </div>
          
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fas fa-store text-purple-500 text-xs"></i>
              Outlet
            </label>
            <select 
              v-model="outletId" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200 bg-white hover:border-gray-300 appearance-none cursor-pointer"
            >
              <option value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                {{ outlet.name }}
              </option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fas fa-check-circle text-purple-500 text-xs"></i>
              Tipe COA
            </label>
            <select 
              v-model="coaType" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200 bg-white hover:border-gray-300 appearance-none cursor-pointer"
            >
              <option value="">Semua Tipe</option>
              <option value="Asset">Asset</option>
              <option value="Liability">Liability</option>
              <option value="Equity">Equity</option>
              <option value="Revenue">Revenue</option>
              <option value="Expense">Expense</option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fas fa-list text-purple-500 text-xs"></i>
              Per Halaman
            </label>
            <select 
              v-model="perPage" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200 bg-white hover:border-gray-300 appearance-none cursor-pointer"
            >
              <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200">
          <button 
            @click="reloadData" 
            :disabled="loadingReload" 
            class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-purple-800 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200"
          >
            <span v-if="loadingReload" class="animate-spin"><i class="fas fa-spinner"></i></span>
            <span v-else><i class="fas fa-sync-alt"></i></span>
            <span>Load Data</span>
          </button>
        </div>
      </div>

      <!-- Summary Cards - Modern Design -->
      <div v-if="summary && hasData" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl shadow-lg p-6 border-2 border-blue-200 hover:shadow-xl transition-all duration-200">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-blue-700 uppercase tracking-wide">Total Asset</span>
            <div class="p-2 bg-blue-200 rounded-lg">
              <i class="fas fa-cube text-blue-700"></i>
            </div>
          </div>
          <div class="text-2xl font-bold text-blue-800">{{ formatRupiah(summary.total_asset) }}</div>
        </div>
        
        <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-2xl shadow-lg p-6 border-2 border-red-200 hover:shadow-xl transition-all duration-200">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-red-700 uppercase tracking-wide">Total Liability</span>
            <div class="p-2 bg-red-200 rounded-lg">
              <i class="fas fa-link text-red-700"></i>
            </div>
          </div>
          <div class="text-2xl font-bold text-red-800">{{ formatRupiah(summary.total_liability) }}</div>
        </div>

        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-2xl shadow-lg p-6 border-2 border-purple-200 hover:shadow-xl transition-all duration-200">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-purple-700 uppercase tracking-wide">Total Equity</span>
            <div class="p-2 bg-purple-200 rounded-lg">
              <i class="fas fa-crown text-purple-700"></i>
            </div>
          </div>
          <div class="text-2xl font-bold text-purple-800">{{ formatRupiah(summary.total_equity) }}</div>
        </div>

        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-2xl shadow-lg p-6 border-2 border-orange-200 hover:shadow-xl transition-all duration-200">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-orange-700 uppercase tracking-wide">Selisih</span>
            <div class="p-2 bg-orange-200 rounded-lg">
              <i class="fas fa-balance-scale text-orange-700"></i>
            </div>
          </div>
          <div class="text-2xl font-bold" :class="summary.difference === 0 ? 'text-gray-700' : 'text-orange-800'">
            {{ formatRupiah(summary.difference) }}
          </div>
        </div>
      </div>

      <!-- Error Message -->
      <div v-if="error" class="bg-gradient-to-r from-red-50 to-red-100 border-2 border-red-300 text-red-800 px-6 py-4 rounded-2xl mb-6 shadow-lg flex items-center gap-3">
        <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
        <span class="font-medium">{{ error }}</span>
      </div>

      <!-- Empty State -->
      <div v-if="!hasData && !loadingReload" class="bg-white rounded-2xl shadow-xl border border-gray-100 p-12 text-center">
        <div class="max-w-md mx-auto">
          <div class="mb-4">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-purple-100 to-purple-200 rounded-full mb-4">
              <i class="fas fa-chart-line text-purple-600 text-3xl"></i>
            </div>
          </div>
          <h3 class="text-xl font-semibold text-gray-800 mb-2">Belum Ada Data</h3>
          <p class="text-gray-600 mb-6">Silakan pilih tanggal dan klik "Load Data" untuk menampilkan laporan Neraca Saldo.</p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loadingReload" class="bg-white rounded-2xl shadow-xl border border-gray-100 p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-purple-100 to-purple-200 rounded-full mb-4">
          <i class="fas fa-spinner fa-spin text-purple-600 text-2xl"></i>
        </div>
        <p class="text-gray-600 font-medium">Memuat data...</p>
      </div>

      <!-- Report Table - Modern Design -->
      <div v-if="hasData && !loadingReload" class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <i class="fas fa-table text-purple-600"></i>
            Data Neraca Saldo
          </h3>
        </div>
        
        <div class="px-6 py-4 flex items-center justify-between border-b border-gray-100 gap-4">
          <div class="flex items-center gap-4">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" v-model="showOnlyNonZero" class="form-checkbox h-4 w-4 text-purple-600" />
              <span>Tampilkan hanya non-zero</span>
            </label>
            <span class="text-sm text-gray-500">(Grouped by COA Type)</span>
          </div>
          <div class="text-sm text-gray-600">Menampilkan grup: <span class="font-semibold">{{ groupedKeys.length }}</span></div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 table-auto">
            <thead class="bg-gradient-to-r from-purple-600 to-purple-700">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">COA</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Nama</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Tipe</th>
                <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">Saldo Debit</th>
                <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">Saldo Kredit</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100 text-sm">
              <template v-if="filteredReport.length === 0">
                <tr>
                  <td colspan="5" class="px-6 py-8 text-center text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2"></i>
                    <div>Tidak ada data.</div>
                  </td>
                </tr>
              </template>

              <template v-else>
                <template v-for="(groupKey, gidx) in groupedKeys" :key="groupKey">
                  <tr class="bg-gray-50">
                    <td colspan="2" class="px-4 py-3 font-semibold text-gray-800">{{ groupKey }}</td>
                    <td class="px-4 py-3">
                      <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold shadow-sm" :class="getTypeColor(groupKey)">
                        {{ groupKey }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-green-700">{{ formatRupiah(groupTotals[groupKey].debit) }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-red-700">{{ formatRupiah(groupTotals[groupKey].kredit) }}</td>
                  </tr>

                  <tr v-for="row in groupedData[groupKey]" :key="row.coa_id" class="hover:bg-purple-50/40 transition-colors duration-150">
                    <td class="px-4 py-3 font-medium">{{ row.coa_code }}</td>
                    <td class="px-4 py-3">{{ row.coa_name }}</td>
                    <td class="px-4 py-3 text-xs text-gray-600">{{ row.coa_type }}</td>
                    <td class="px-4 py-3 text-right" :class="row.saldo_debit !== 0 ? 'text-green-700 font-semibold' : 'text-gray-400'">{{ formatRupiah(row.saldo_debit) }}</td>
                    <td class="px-4 py-3 text-right" :class="row.saldo_kredit !== 0 ? 'text-red-700 font-semibold' : 'text-gray-400'">{{ formatRupiah(row.saldo_kredit) }}</td>
                  </tr>
                </template>

                <!-- Grand Total Row -->
                <tr class="bg-gradient-to-r from-purple-100 to-purple-50 font-bold border-t-2 border-purple-300">
                  <td colspan="3" class="px-4 py-3 text-right">TOTAL</td>
                  <td class="px-4 py-3 text-right text-green-700">{{ formatRupiah(summary.total_debit) }}</td>
                  <td class="px-4 py-3 text-right text-red-700">{{ formatRupiah(summary.total_kredit) }}</td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination - Modern Design -->
      <div v-if="hasData && !loadingReload" class="flex flex-col sm:flex-row justify-between items-center gap-4 mt-6 bg-white rounded-2xl shadow-lg border border-gray-100 px-6 py-4">
        <div class="text-sm text-gray-600 flex items-center gap-2">
          <i class="fas fa-info-circle text-purple-500"></i>
          <span>Menampilkan <span class="font-semibold text-gray-900">{{ startIndex + 1 }}</span> - <span class="font-semibold text-gray-900">{{ endIndex }}</span> dari <span class="font-semibold text-gray-900">{{ total }}</span> data</span>
        </div>
        <div class="flex items-center gap-2">
          <button 
            @click="prevPage" 
            :disabled="page === 1 || loadingReload" 
            class="px-4 py-2 rounded-xl border-2 text-sm font-medium transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed" 
            :class="page === 1 ? 'bg-gray-100 text-gray-400 border-gray-200' : 'bg-white text-purple-700 border-purple-300 hover:bg-purple-50 hover:border-purple-400 hover:shadow-md'"
          >
            <i class="fas fa-chevron-left"></i>
          </button>
          <span class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-50 rounded-xl border border-gray-200">
            Halaman {{ page }} / {{ totalPages }}
          </span>
          <button 
            @click="nextPage" 
            :disabled="page === totalPages || loadingReload" 
            class="px-4 py-2 rounded-xl border-2 text-sm font-medium transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed" 
            :class="page === totalPages ? 'bg-gray-100 text-gray-400 border-gray-200' : 'bg-white text-purple-700 border-purple-300 hover:bg-purple-50 hover:border-purple-400 hover:shadow-md'"
          >
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  report: Object,
  summary: Object,
  outlets: Array,
  filters: Object,
  error: String,
});

const dateAs = ref(props.filters?.date_as || new Date().toISOString().split('T')[0]);
const outletId = ref(props.filters?.outlet_id || '');
const coaType = ref(props.filters?.coa_type || '');
const perPage = ref(props.filters?.per_page || 25);
const page = ref(props.filters?.page || 1);
const loadingReload = ref(false);

const startIndex = computed(() => {
  if (!props.report || !props.report.from) return 0;
  return props.report.from - 1;
});

const endIndex = computed(() => {
  if (!props.report || !props.report.to) return 0;
  return props.report.to;
});

const totalPages = computed(() => {
  if (!props.report || !props.report.last_page) return 1;
  return props.report.last_page;
});

const report = computed(() => {
  return props.report?.data || [];
});

const total = computed(() => {
  return props.report?.total || 0;
});

const hasData = computed(() => {
  return props.report && props.report.data && props.report.data.length > 0;
});

// UI: toggle to show only non-zero balances
const showOnlyNonZero = ref(true);

// Filtered report (apply non-zero filter)
const filteredReport = computed(() => {
  const rows = report.value || [];
  if (!showOnlyNonZero.value) return rows;
  return rows.filter(r => (Number(r.saldo_debit) || Number(r.saldo_kredit)));
});

// Group by COA type
const groupedData = computed(() => {
  const groups = {};
  (filteredReport.value || []).forEach(r => {
    const t = r.coa_type || 'Other';
    if (!groups[t]) groups[t] = [];
    groups[t].push(r);
  });
  return groups;
});

// Maintain consistent group order
const groupOrder = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];
const groupedKeys = computed(() => {
  const keys = Object.keys(groupedData.value || {});
  keys.sort((a, b) => {
    const ia = groupOrder.indexOf(a) === -1 ? 999 : groupOrder.indexOf(a);
    const ib = groupOrder.indexOf(b) === -1 ? 999 : groupOrder.indexOf(b);
    return ia - ib;
  });
  return keys;
});

const groupTotals = computed(() => {
  const t = {};
  Object.entries(groupedData.value || {}).forEach(([k, rows]) => {
    t[k] = { debit: 0, kredit: 0 };
    rows.forEach(r => {
      t[k].debit += Number(r.saldo_debit || 0);
      t[k].kredit += Number(r.saldo_kredit || 0);
    });
  });
  return t;
});

watch([dateAs, outletId, coaType, perPage], () => {
  page.value = 1;
});

function reloadData() {
  loadingReload.value = true;
  router.get(
    '/report-jurnal-neraca-saldo',
    {
      date_as: dateAs.value,
      outlet_id: outletId.value || null,
      coa_type: coaType.value || null,
      per_page: perPage.value,
      page: page.value,
      load_data: true,
    },
    {
      preserveState: false,
      preserveScroll: false,
      onFinish: () => {
        loadingReload.value = false;
      },
    }
  );
}

function prevPage() {
  if (page.value > 1 && !loadingReload.value) {
    page.value--;
    reloadData();
  }
}

function nextPage() {
  if (page.value < totalPages.value && !loadingReload.value) {
    page.value++;
    reloadData();
  }
}

function formatRupiah(value) {
  if (value === null || value === undefined) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value);
}

function getTypeColor(type) {
  const colors = {
    'Asset': 'bg-blue-100 text-blue-800 border border-blue-200',
    'Liability': 'bg-red-100 text-red-800 border border-red-200',
    'Equity': 'bg-purple-100 text-purple-800 border border-purple-200',
    'Revenue': 'bg-green-100 text-green-800 border border-green-200',
    'Expense': 'bg-orange-100 text-orange-800 border border-orange-200',
  };
  return colors[type] || 'bg-gray-100 text-gray-800 border border-gray-200';
}
</script>

<style scoped>
.overflow-x-auto::-webkit-scrollbar {
  height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 10px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

* {
  transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 150ms;
}
</style>
