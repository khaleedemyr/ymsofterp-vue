<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 via-green-50/30 to-gray-50 py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2 flex items-center gap-3">
              <div class="p-2 bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg">
                <i class="fas fa-water text-white text-xl"></i>
              </div>
              <span>Laporan Arus Kas</span>
              <span class="text-lg font-normal text-gray-500">(Cash Flow Statement)</span>
            </h1>
            <p class="text-sm text-gray-600 ml-14">Analisis pergerakan kas masuk dan kas keluar dalam periode tertentu</p>
          </div>
        </div>
      </div>

      <!-- Filter Card - Modern Design -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mb-6 backdrop-blur-sm">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <i class="fas fa-filter text-green-600"></i>
            Filter Laporan
          </h2>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fas fa-calendar-alt text-green-500 text-xs"></i>
              Tanggal Dari
            </label>
            <input 
              v-model="dateFrom" 
              type="date" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 bg-white hover:border-gray-300"
            />
          </div>
          
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fas fa-calendar-check text-green-500 text-xs"></i>
              Tanggal Sampai
            </label>
            <input 
              v-model="dateTo" 
              type="date" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 bg-white hover:border-gray-300"
            />
          </div>
          
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fas fa-store text-green-500 text-xs"></i>
              Outlet
            </label>
            <select 
              v-model="outletId" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 bg-white hover:border-gray-300 appearance-none cursor-pointer"
            >
              <option value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                {{ outlet.name }}
              </option>
            </select>
          </div>

          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700 flex items-center gap-2">
              <i class="fas fa-list text-green-500 text-xs"></i>
              Per Halaman
            </label>
            <select 
              v-model="perPage" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 bg-white hover:border-gray-300 appearance-none cursor-pointer"
            >
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
            </select>
          </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200">
          <button 
            @click="reloadData" 
            :disabled="loadingReload" 
            class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl font-semibold hover:from-green-700 hover:to-green-800 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200"
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
            <span class="text-sm font-medium text-blue-700 uppercase tracking-wide">Saldo Awal</span>
            <div class="p-2 bg-blue-200 rounded-lg">
              <i class="fas fa-arrow-right text-blue-700"></i>
            </div>
          </div>
          <div class="text-2xl font-bold text-blue-800">{{ formatRupiah(summary.opening_balance) }}</div>
        </div>
        
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl shadow-lg p-6 border-2 border-green-200 hover:shadow-xl transition-all duration-200">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-green-700 uppercase tracking-wide">Kas Masuk</span>
            <div class="p-2 bg-green-200 rounded-lg">
              <i class="fas fa-arrow-down text-green-700"></i>
            </div>
          </div>
          <div class="text-2xl font-bold text-green-800">{{ formatRupiah(summary.total_inflow) }}</div>
        </div>

        <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-2xl shadow-lg p-6 border-2 border-red-200 hover:shadow-xl transition-all duration-200">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-red-700 uppercase tracking-wide">Kas Keluar</span>
            <div class="p-2 bg-red-200 rounded-lg">
              <i class="fas fa-arrow-up text-red-700"></i>
            </div>
          </div>
          <div class="text-2xl font-bold text-red-800">{{ formatRupiah(summary.total_outflow) }}</div>
        </div>

        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-2xl shadow-lg p-6 border-2 border-orange-200 hover:shadow-xl transition-all duration-200">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-orange-700 uppercase tracking-wide">Saldo Akhir</span>
            <div class="p-2 bg-orange-200 rounded-lg">
              <i class="fas fa-balance-scale text-orange-700"></i>
            </div>
          </div>
          <div class="text-2xl font-bold text-orange-800">{{ formatRupiah(summary.closing_balance) }}</div>
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
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-green-100 to-green-200 rounded-full mb-4">
              <i class="fas fa-chart-line text-green-600 text-3xl"></i>
            </div>
          </div>
          <h3 class="text-xl font-semibold text-gray-800 mb-2">Belum Ada Data</h3>
          <p class="text-gray-600 mb-6">Silakan isi filter di atas dan klik "Load Data" untuk menampilkan laporan Arus Kas.</p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loadingReload" class="bg-white rounded-2xl shadow-xl border border-gray-100 p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-green-100 to-green-200 rounded-full mb-4">
          <i class="fas fa-spinner fa-spin text-green-600 text-2xl"></i>
        </div>
        <p class="text-gray-600 font-medium">Memuat data...</p>
      </div>

      <!-- Report Section -->
      <div v-if="hasData && !loadingReload" class="space-y-6">
        <!-- Aktivitas Operasi Section -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
          <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-blue-100 border-b-2 border-blue-300">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
              <i class="fas fa-cogs text-blue-600"></i>
              Aktivitas Operasi
            </h3>
          </div>
          
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gradient-to-r from-blue-100 to-blue-50">
                <tr>
                  <th class="px-6 py-4 text-left text-xs font-bold text-blue-900 uppercase tracking-wider">Uraian</th>
                  <th class="px-6 py-4 text-right text-xs font-bold text-blue-900 uppercase tracking-wider">Jumlah</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr class="hover:bg-blue-50/50 transition-colors">
                  <td class="px-6 py-4">
                    <span class="font-semibold text-gray-900">Revenue Operasi</span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <span class="font-semibold text-green-700">{{ formatRupiah(flowData.operational.revenue) }}</span>
                  </td>
                </tr>
                <tr class="hover:bg-blue-50/50 transition-colors">
                  <td class="px-6 py-4">
                    <span class="font-semibold text-gray-900">Beban Operasi</span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <span class="font-semibold text-red-700">({{ formatRupiah(flowData.operational.expenses) }})</span>
                  </td>
                </tr>
                <tr class="bg-blue-50 border-t-2 border-blue-300">
                  <td class="px-6 py-4 font-bold">
                    <span class="text-blue-900">Arus Kas Operasi</span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <span class="font-bold text-blue-700">{{ formatRupiah(flowData.operational.net) }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Aktivitas Investasi Section -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
          <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-purple-100 border-b-2 border-purple-300">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
              <i class="fas fa-chart-pie text-purple-600"></i>
              Aktivitas Investasi
            </h3>
          </div>
          
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gradient-to-r from-purple-100 to-purple-50">
                <tr>
                  <th class="px-6 py-4 text-left text-xs font-bold text-purple-900 uppercase tracking-wider">Uraian</th>
                  <th class="px-6 py-4 text-right text-xs font-bold text-purple-900 uppercase tracking-wider">Jumlah</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="flowData.investment.items.length === 0" class="hover:bg-purple-50/50 transition-colors">
                  <td colspan="2" class="px-6 py-8 text-center text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2"></i>
                    <p class="text-sm">Tidak ada transaksi investasi</p>
                  </td>
                </tr>
                <tr v-for="item in flowData.investment.items" :key="item.id" class="hover:bg-purple-50/50 transition-colors">
                  <td class="px-6 py-4">
                    <span class="font-semibold text-gray-900">{{ item.description }}</span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <span class="font-semibold" :class="item.amount >= 0 ? 'text-green-700' : 'text-red-700'">
                      {{ item.amount >= 0 ? formatRupiah(item.amount) : `(${formatRupiah(Math.abs(item.amount))})` }}
                    </span>
                  </td>
                </tr>
                <tr class="bg-purple-50 border-t-2 border-purple-300">
                  <td class="px-6 py-4 font-bold">
                    <span class="text-purple-900">Arus Kas Investasi</span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <span class="font-bold text-purple-700">{{ formatRupiah(flowData.investment.net) }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Aktivitas Pendanaan Section -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
          <div class="px-6 py-4 bg-gradient-to-r from-pink-50 to-pink-100 border-b-2 border-pink-300">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
              <i class="fas fa-money-bill-wave text-pink-600"></i>
              Aktivitas Pendanaan
            </h3>
          </div>
          
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gradient-to-r from-pink-100 to-pink-50">
                <tr>
                  <th class="px-6 py-4 text-left text-xs font-bold text-pink-900 uppercase tracking-wider">Uraian</th>
                  <th class="px-6 py-4 text-right text-xs font-bold text-pink-900 uppercase tracking-wider">Jumlah</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="flowData.financing.items.length === 0" class="hover:bg-pink-50/50 transition-colors">
                  <td colspan="2" class="px-6 py-8 text-center text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2"></i>
                    <p class="text-sm">Tidak ada transaksi pendanaan</p>
                  </td>
                </tr>
                <tr v-for="item in flowData.financing.items" :key="item.id" class="hover:bg-pink-50/50 transition-colors">
                  <td class="px-6 py-4">
                    <span class="font-semibold text-gray-900">{{ item.description }}</span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <span class="font-semibold" :class="item.amount >= 0 ? 'text-green-700' : 'text-red-700'">
                      {{ item.amount >= 0 ? formatRupiah(item.amount) : `(${formatRupiah(Math.abs(item.amount))})` }}
                    </span>
                  </td>
                </tr>
                <tr class="bg-pink-50 border-t-2 border-pink-300">
                  <td class="px-6 py-4 font-bold">
                    <span class="text-pink-900">Arus Kas Pendanaan</span>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <span class="font-bold text-pink-700">{{ formatRupiah(flowData.financing.net) }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Summary Section -->
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-2xl shadow-xl border-2 border-gray-300 p-6">
          <div class="space-y-3">
            <div class="flex justify-between items-center pb-3 border-b border-gray-300">
              <span class="text-gray-700 font-semibold">Saldo Awal Kas</span>
              <span class="text-lg font-bold text-gray-900">{{ formatRupiah(summary.opening_balance) }}</span>
            </div>
            <div class="flex justify-between items-center pb-3 border-b border-gray-300">
              <span class="text-gray-700 font-semibold">Arus Kas Operasi</span>
              <span class="text-lg font-bold" :class="flowData.operational.net >= 0 ? 'text-green-700' : 'text-red-700'">
                {{ formatRupiah(flowData.operational.net) }}
              </span>
            </div>
            <div class="flex justify-between items-center pb-3 border-b border-gray-300">
              <span class="text-gray-700 font-semibold">Arus Kas Investasi</span>
              <span class="text-lg font-bold" :class="flowData.investment.net >= 0 ? 'text-green-700' : 'text-red-700'">
                {{ formatRupiah(flowData.investment.net) }}
              </span>
            </div>
            <div class="flex justify-between items-center pb-3 border-b border-gray-300">
              <span class="text-gray-700 font-semibold">Arus Kas Pendanaan</span>
              <span class="text-lg font-bold" :class="flowData.financing.net >= 0 ? 'text-green-700' : 'text-red-700'">
                {{ formatRupiah(flowData.financing.net) }}
              </span>
            </div>
            <div class="flex justify-between items-center pt-3 border-t-2 border-gray-400 bg-white rounded-lg p-4">
              <span class="text-gray-900 font-bold text-lg">Saldo Akhir Kas</span>
              <span class="text-2xl font-bold text-orange-700">{{ formatRupiah(summary.closing_balance) }}</span>
            </div>
          </div>
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

const dateFrom = ref(props.filters?.date_from || new Date().toISOString().split('T')[0].slice(0, -2) + '01');
const dateTo = ref(props.filters?.date_to || new Date().toISOString().split('T')[0]);
const outletId = ref(props.filters?.outlet_id || '');
const perPage = ref(props.filters?.per_page || 25);
const loadingReload = ref(false);

const report = computed(() => {
  return props.report || {};
});

const hasData = computed(() => {
  return props.report && Object.keys(props.report).length > 0;
});

const flowData = computed(() => {
  if (!hasData.value) {
    return {
      operational: { revenue: 0, expenses: 0, net: 0 },
      investment: { items: [], net: 0 },
      financing: { items: [], net: 0 }
    };
  }
  return props.report;
});

watch([dateFrom, dateTo, outletId, perPage], () => {
  // Reset on filter change
});

function reloadData() {
  loadingReload.value = true;
  router.get(
    '/report-arus-kas',
    {
      date_from: dateFrom.value,
      date_to: dateTo.value,
      outlet_id: outletId.value || null,
      per_page: perPage.value,
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

function formatRupiah(value) {
  if (value === null || value === undefined) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value);
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
