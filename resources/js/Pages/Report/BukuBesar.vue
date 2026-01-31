<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 via-blue-50/30 to-gray-50 py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2 flex items-center gap-3">
              <div class="p-2 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg">
                <i class="fas fa-book-open text-white text-xl"></i>
              </div>
              <span>Buku Besar</span>
              <span class="text-lg font-normal text-gray-500">(General Ledger)</span>
            </h1>
            <p class="text-sm text-gray-600 ml-14">Laporan detail transaksi per Chart of Account</p>
          </div>
        </div>
      </div>

      <!-- Filter Card - Modern Design -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mb-6 backdrop-blur-sm">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <i class="fas fa-filter text-blue-600"></i>
            Filter Laporan
          </h2>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fas fa-calendar-alt text-blue-500 text-xs"></i>
              Tanggal Dari
            </label>
            <input 
              v-model="dateFrom" 
              type="date" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white hover:border-gray-300"
            />
          </div>
          
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fas fa-calendar-check text-blue-500 text-xs"></i>
              Tanggal Sampai
            </label>
            <input 
              v-model="dateTo" 
              type="date" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white hover:border-gray-300"
            />
          </div>
          
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fas fa-store text-blue-500 text-xs"></i>
              Outlet
            </label>
            <select 
              v-model="outletId" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white hover:border-gray-300 appearance-none cursor-pointer"
            >
              <option value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                {{ outlet.name }}
              </option>
            </select>
          </div>
          
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fas fa-chart-line text-blue-500 text-xs"></i>
              Chart of Account
            </label>
            <select 
              v-model="coaId" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white hover:border-gray-300 appearance-none cursor-pointer"
            >
              <option value="">Semua COA</option>
              <option v-for="coa in coas" :key="coa.id" :value="coa.id">
                {{ coa.code }} - {{ coa.name }}
              </option>
            </select>
          </div>
          
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fas fa-check-circle text-blue-500 text-xs"></i>
              Status
            </label>
            <select 
              v-model="status" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white hover:border-gray-300 appearance-none cursor-pointer"
            >
              <option value="posted">Posted</option>
              <option value="draft">Draft</option>
              <option value="cancelled">Cancelled</option>
              <option value="">Semua Status</option>
            </select>
          </div>
          
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fas fa-list text-blue-500 text-xs"></i>
              Per Halaman
            </label>
            <select 
              v-model="perPage" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white hover:border-gray-300 appearance-none cursor-pointer"
            >
              <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>

          <div class="space-y-1.5 flex items-center">
            <label class="inline-flex items-center gap-2">
              <input type="checkbox" v-model="onlyWithCredit" class="h-4 w-4 text-blue-600 border-gray-300 rounded" />
              <span class="text-sm text-gray-700">Tampilkan hanya COA dengan Kredit</span>
            </label>
          </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200">
          <button 
            @click="reloadData" 
            :disabled="loadingReload" 
            class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200"
          >
            <span v-if="loadingReload" class="animate-spin"><i class="fas fa-spinner"></i></span>
            <span v-else><i class="fas fa-sync-alt"></i></span>
            <span>Load Data</span>
          </button>
          
          <button 
            v-if="hasData"
            @click="expandAll" 
            class="inline-flex items-center gap-2 px-5 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl font-semibold hover:from-green-700 hover:to-green-800 shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200"
          >
            <i class="fas fa-expand"></i>
            <span>Expand All</span>
          </button>
          
          <button 
            v-if="hasData"
            @click="collapseAll" 
            class="inline-flex items-center gap-2 px-5 py-3 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl font-semibold hover:from-gray-700 hover:to-gray-800 shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200"
          >
            <i class="fas fa-compress"></i>
            <span>Collapse All</span>
          </button>
        </div>
      </div>

      <!-- Summary Cards - Modern Design -->
      <div v-if="summary && hasData" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl shadow-lg p-6 border-2 border-green-200 hover:shadow-xl transition-all duration-200">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-green-700 uppercase tracking-wide">Total Debit</span>
            <div class="p-2 bg-green-200 rounded-lg">
              <i class="fas fa-arrow-down text-green-700"></i>
            </div>
          </div>
          <div class="text-2xl font-bold text-green-800">{{ formatRupiah(summary.total_debit) }}</div>
        </div>
        
        <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-2xl shadow-lg p-6 border-2 border-red-200 hover:shadow-xl transition-all duration-200">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-red-700 uppercase tracking-wide">Total Kredit</span>
            <div class="p-2 bg-red-200 rounded-lg">
              <i class="fas fa-arrow-up text-red-700"></i>
            </div>
          </div>
          <div class="text-2xl font-bold text-red-800">{{ formatRupiah(summary.total_credit) }}</div>
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
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-blue-100 to-blue-200 rounded-full mb-4">
              <i class="fas fa-chart-line text-blue-600 text-3xl"></i>
            </div>
          </div>
          <h3 class="text-xl font-semibold text-gray-800 mb-2">Belum Ada Data</h3>
          <p class="text-gray-600 mb-6">Silakan isi filter di atas dan klik "Load Data" untuk menampilkan laporan Buku Besar.</p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loadingReload" class="bg-white rounded-2xl shadow-xl border border-gray-100 p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-100 to-blue-200 rounded-full mb-4">
          <i class="fas fa-spinner fa-spin text-blue-600 text-2xl"></i>
        </div>
        <p class="text-gray-600 font-medium">Memuat data...</p>
      </div>

      <!-- Report Table - Modern Design -->
      <div v-if="hasData && !loadingReload" class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <i class="fas fa-table text-blue-600"></i>
            Data Buku Besar
          </h3>
        </div>
        
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-600 to-blue-700">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider w-12"></th>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Kode COA</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Nama COA</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Tipe</th>
                <th class="px-6 py-4 text-right text-xs font-bold text-white uppercase tracking-wider">Saldo Awal</th>
                <th class="px-6 py-4 text-right text-xs font-bold text-white uppercase tracking-wider">Total Debit</th>
                <th class="px-6 py-4 text-right text-xs font-bold text-white uppercase tracking-wider">Total Kredit</th>
                <th class="px-6 py-4 text-right text-xs font-bold text-white uppercase tracking-wider">Saldo Akhir</th>
                <th class="px-6 py-4 text-center text-xs font-bold text-white uppercase tracking-wider">Jumlah Transaksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <template v-if="!report || report.length === 0">
                <tr>
                  <td colspan="9" class="px-6 py-12 text-center">
                    <div class="text-gray-400">
                      <i class="fas fa-inbox text-4xl mb-3"></i>
                      <p class="text-sm">Tidak ada data.</p>
                    </div>
                  </td>
                </tr>
              </template>
              <template v-else>
                <template v-for="(row, idx) in report" :key="row.coa_id">
                  <!-- COA Summary Row -->
                  <tr 
                    class="hover:bg-blue-50/50 cursor-pointer transition-all duration-150" 
                    :class="expandedRows[row.coa_id] ? 'bg-blue-50 border-l-4 border-blue-500' : 'hover:border-l-4 hover:border-blue-300'"
                    @click="toggleRow(row.coa_id)"
                  >
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                      <div class="inline-flex items-center justify-center w-8 h-8 rounded-full transition-all duration-200" :class="expandedRows[row.coa_id] ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-700'">
                        <i 
                          class="fas text-sm transition-transform duration-200" 
                          :class="expandedRows[row.coa_id] ? 'fa-chevron-down' : 'fa-chevron-right'"
                        ></i>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="font-semibold text-gray-900">{{ row.coa_code }}</span>
                    </td>
                    <td class="px-6 py-4">
                      <span class="font-semibold text-gray-900">{{ row.coa_name }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold shadow-sm" :class="getTypeColor(row.coa_type)">
                        {{ row.coa_type }}
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                      <span class="font-semibold text-gray-900">{{ formatRupiah(row.opening_balance) }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                      <span :class="isNormalCredit(row.coa_type) ? 'font-semibold text-gray-500' : 'font-semibold text-green-700'">{{ formatRupiah(row.total_debit) }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                      <span :class="isNormalCredit(row.coa_type) ? 'font-semibold text-red-700' : 'font-semibold text-gray-500'">{{ formatRupiah(row.total_credit) }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                      <span class="font-semibold text-gray-900">{{ formatRupiah(row.closing_balance) }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                      <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                        {{ row.transaction_count }}
                      </span>
                    </td>
                  </tr>
                  
                  <!-- Transaction Detail Rows (when expanded) -->
                  <template v-if="expandedRows[row.coa_id] && row.transactions && row.transactions.length > 0">
                    <tr v-for="(tx, txIdx) in row.transactions" :key="tx.id" class="bg-gray-50/50 hover:bg-gray-100/50 transition-colors">
                      <td class="px-6 py-3"></td>
                      <td class="px-6 py-3 text-xs text-gray-600 font-medium" colspan="2">
                        <div class="flex items-center gap-2">
                          <i class="fas fa-calendar text-gray-400"></i>
                          <span>{{ formatDate(tx.tanggal) }}</span>
                          <span class="text-gray-400">|</span>
                          <i class="fas fa-hashtag text-gray-400"></i>
                          <span>{{ tx.no_jurnal }}</span>
                        </div>
                      </td>
                      <td class="px-6 py-3 text-xs text-gray-700">
                        <div class="max-w-xs truncate" :title="tx.keterangan">
                          {{ tx.keterangan }}
                        </div>
                      </td>
                      <td class="px-6 py-3 text-xs text-gray-600">
                        <span v-if="tx.nama_outlet" class="inline-flex items-center gap-1">
                          <i class="fas fa-store text-gray-400"></i>
                          {{ tx.nama_outlet }}
                        </span>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="px-6 py-3 text-right text-xs" :class="tx.coa_debit_id == row.coa_id ? 'text-green-700 font-bold' : 'text-gray-400'">
                        {{ tx.coa_debit_id == row.coa_id ? formatRupiah(tx.jumlah_debit) : '-' }}
                      </td>
                      <td class="px-6 py-3 text-right text-xs" :class="tx.coa_kredit_id == row.coa_id ? 'text-red-700 font-bold' : 'text-gray-400'">
                        {{ tx.coa_kredit_id == row.coa_id ? formatRupiah(tx.jumlah_kredit) : '-' }}
                      </td>
                      <td class="px-6 py-3 text-right text-xs font-semibold text-gray-800">
                        {{ formatRupiah(tx.saldo) }}
                      </td>
                      <td class="px-6 py-3 text-xs text-gray-600">
                        <span class="inline-flex items-center gap-1">
                          <i class="fas fa-link text-gray-400"></i>
                          <span class="truncate max-w-xs" :title="tx.reference_type + ': ' + tx.reference_id">
                            {{ tx.reference_type }}: {{ tx.reference_id }}
                          </span>
                        </span>
                      </td>
                    </tr>
                  </template>
                </template>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination - Modern Design -->
      <div v-if="hasData && !loadingReload" class="flex flex-col sm:flex-row justify-between items-center gap-4 mt-6 bg-white rounded-2xl shadow-lg border border-gray-100 px-6 py-4">
        <div class="text-sm text-gray-600 flex items-center gap-2">
          <i class="fas fa-info-circle text-blue-500"></i>
          <span>Menampilkan <span class="font-semibold text-gray-900">{{ startIndex + 1 }}</span> - <span class="font-semibold text-gray-900">{{ endIndex }}</span> dari <span class="font-semibold text-gray-900">{{ total }}</span> data</span>
        </div>
        <div class="flex items-center gap-2">
          <button 
            @click="prevPage" 
            :disabled="page === 1 || loadingReload" 
            class="px-4 py-2 rounded-xl border-2 text-sm font-medium transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed" 
            :class="page === 1 ? 'bg-gray-100 text-gray-400 border-gray-200' : 'bg-white text-blue-700 border-blue-300 hover:bg-blue-50 hover:border-blue-400 hover:shadow-md'"
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
            :class="page === totalPages ? 'bg-gray-100 text-gray-400 border-gray-200' : 'bg-white text-blue-700 border-blue-300 hover:bg-blue-50 hover:border-blue-400 hover:shadow-md'"
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
  coas: Array,
  filters: Object,
  error: String,
});

const dateFrom = ref(props.filters?.date_from || new Date().toISOString().split('T')[0].slice(0, -2) + '01');
const dateTo = ref(props.filters?.date_to || new Date().toISOString().split('T')[0]);
const outletId = ref(props.filters?.outlet_id || '');
const coaId = ref(props.filters?.coa_id || '');
const status = ref(props.filters?.status || 'posted');
const perPage = ref(props.filters?.per_page || 25);
const page = ref(props.filters?.page || 1);
const onlyWithCredit = ref(props.filters?.only_with_credit || false);
const loadingReload = ref(false);
const expandedRows = ref({});

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

watch([dateFrom, dateTo, outletId, coaId, status, perPage], () => {
  page.value = 1;
});

function reloadData() {
  loadingReload.value = true;
  router.get(
    '/report-jurnal-buku-besar',
    {
      date_from: dateFrom.value,
      date_to: dateTo.value,
      outlet_id: outletId.value || null,
      only_with_credit: onlyWithCredit.value || null,
      coa_id: coaId.value || null,
      status: status.value,
      per_page: perPage.value,
      page: page.value,
      load_data: true, // Flag untuk load data
    },
    {
      preserveState: false, // Reset state saat load data baru
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

function toggleRow(coaId) {
  if (!hasData.value) return;
  expandedRows.value[coaId] = !expandedRows.value[coaId];
}

function expandAll() {
  if (!report.value) return;
  report.value.forEach(row => {
    expandedRows.value[row.coa_id] = true;
  });
}

function collapseAll() {
  expandedRows.value = {};
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

function formatDate(dateString) {
  if (!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
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

function isNormalCredit(type) {
  return ['Liability', 'Equity', 'Revenue'].includes(type);
}
</script>

<style scoped>
/* Custom scrollbar untuk table */
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

/* Smooth transitions */
* {
  transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 150ms;
}
</style>
