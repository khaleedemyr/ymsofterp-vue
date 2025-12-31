<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CrmStats from './Components/CrmStats.vue';
import CrmGrowthChart from './Components/CrmGrowthChart.vue';
import CrmDemographicsChart from './Components/CrmDemographicsChart.vue';
import CrmPurchasingPowerChart from './Components/CrmPurchasingPowerChart.vue';

import CrmLatestMembers from './Components/CrmLatestMembers.vue';
import CrmActivityList from './Components/CrmActivityList.vue';
import RedeemDetailsModal from './Components/RedeemDetailsModal.vue';
import CrmMemberDemographics from './Components/CrmMemberDemographics.vue';

const props = defineProps({
  stats: Object,
  memberGrowth: Array,
  memberDemographics: Object,
  purchasingPowerByAge: Array,
  memberDemographicsByRegion: Array,

  latestMembers: Array,
  memberActivity: Array,
  pointStats: Object,
  pointTransactions: Array,
  pointByCabang: Array,
  filters: Object,
});

// Reactive data untuk filter tanggal
const dateFilters = ref({
  start_date: props.filters?.start_date || '',
  end_date: props.filters?.end_date || '',
});

// Reactive data untuk modal redeem details
const redeemModal = ref({
  isOpen: false,
  cabangId: null,
  cabangName: '',
});

function formatNumber(number) {
  return number.toLocaleString('id-ID');
}

function formatRupiah(amount) {
  return 'Rp ' + amount.toLocaleString('id-ID');
}

function goToMembers() {
  router.visit('/members');
}

function goToCreateMember() {
  router.visit('/members/create');
}

function applyDateFilter() {
  router.visit('/crm/dashboard', {
    data: {
      start_date: dateFilters.value.start_date,
      end_date: dateFilters.value.end_date,
    },
    preserveState: true,
  });
}

function clearDateFilter() {
  dateFilters.value.start_date = '';
  dateFilters.value.end_date = '';
  router.visit('/crm/dashboard', {
    data: {
      start_date: '',
      end_date: '',
    },
    preserveState: true,
  });
}

function openRedeemModal(cabangId, cabangName) {
  redeemModal.value = {
    isOpen: true,
    cabangId: cabangId,
    cabangName: cabangName,
  };
}

function closeRedeemModal() {
  redeemModal.value.isOpen = false;
}
</script>

<template>
  <AppLayout title="Dashboard CRM">
    <div class="w-full py-2 px-2">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Dashboard CRM</h1>
            <p class="mt-2 text-gray-600">Overview dan analisis data member</p>
          </div>
          <div class="flex gap-3">
            <button
              @click="goToMembers"
              class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition flex items-center gap-2"
            >
              <i class="fa-solid fa-users"></i>
              Lihat Semua Member
            </button>
            <button
              @click="goToCreateMember"
              class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition flex items-center gap-2"
            >
              <i class="fa-solid fa-plus"></i>
              Tambah Member
            </button>
          </div>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="mb-8">
        <CrmStats :stats="stats" />
      </div>

      <!-- Charts Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Member Growth Chart -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-purple-500"></i>
            Pertumbuhan Member (12 Bulan Terakhir)
          </h3>
          <CrmGrowthChart :data="memberGrowth" />
        </div>

        <!-- Demographics Chart -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-chart-pie text-blue-500"></i>
            Demografi Member
          </h3>
          <CrmDemographicsChart :data="memberDemographics" />
        </div>
      </div>

      <!-- Purchasing Power Chart -->
      <div class="mb-8">
        <CrmPurchasingPowerChart :data="purchasingPowerByAge" :filters="filters" />
      </div>

      <!-- Member Demographics by Region -->
      <div class="mb-8">
        <CrmMemberDemographics :member-demographics="memberDemographicsByRegion" />
      </div>



      <!-- Point Statistics -->
      <div class="mb-8">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <i class="fa-solid fa-coins text-yellow-500"></i>
            Statistik Point Member
          </h2>
          
          <!-- Date Filter for Point Statistics -->
          <div class="flex gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
              <input
                v-model="dateFilters.start_date"
                type="date"
                class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
              <input
                v-model="dateFilters.end_date"
                type="date"
                class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <div class="flex gap-2 items-end">
              <button
                @click="applyDateFilter"
                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition flex items-center gap-2"
              >
                <i class="fa-solid fa-filter"></i>
                Filter
              </button>
              <button
                @click="clearDateFilter"
                class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition flex items-center gap-2"
              >
                <i class="fa-solid fa-times"></i>
                Reset
              </button>
            </div>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Total Transactions -->
          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Total Transaksi</p>
                <p class="text-2xl font-bold text-gray-900">{{ formatNumber(pointStats.totalTransactions) }}</p>
              </div>
              <div class="bg-blue-100 p-3 rounded-lg">
                <i class="fa-solid fa-receipt text-blue-600 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- Transactions Today -->
          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Transaksi Hari Ini</p>
                <p class="text-2xl font-bold text-gray-900">{{ formatNumber(pointStats.transactionsToday) }}</p>
              </div>
              <div class="bg-green-100 p-3 rounded-lg">
                <i class="fa-solid fa-calendar-day text-green-600 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- Transactions This Month -->
          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Transaksi Bulan Ini</p>
                <p class="text-2xl font-bold text-gray-900">{{ formatNumber(pointStats.transactionsThisMonth) }}</p>
                <div class="flex items-center gap-1 mt-1">
                  <i :class="pointStats.growthRate > 0 ? 'fa-solid fa-arrow-up text-green-500' : 'fa-solid fa-arrow-down text-red-500'"></i>
                  <span :class="['text-sm font-medium', pointStats.growthRate > 0 ? 'text-green-600' : 'text-red-600']">
                    {{ pointStats.growthRate > 0 ? '+' : '' }}{{ pointStats.growthRate }}%
                  </span>
                  <span class="text-xs text-gray-500">vs bulan lalu</span>
                </div>
              </div>
              <div class="bg-purple-100 p-3 rounded-lg">
                <i class="fa-solid fa-chart-line text-purple-600 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- Total Point Earned -->
          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-emerald-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Total Point Diperoleh</p>
                <p class="text-2xl font-bold text-gray-900">{{ pointStats.totalPointEarnedFormatted }}</p>
              </div>
              <div class="bg-emerald-100 p-3 rounded-lg">
                <i class="fa-solid fa-plus-circle text-emerald-600 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- Total Point Redeemed -->
          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Total Point Ditukar</p>
                <p class="text-2xl font-bold text-gray-900">{{ pointStats.totalPointRedeemedFormatted }}</p>
              </div>
              <div class="bg-orange-100 p-3 rounded-lg">
                <i class="fa-solid fa-minus-circle text-orange-600 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- Total Transaction Value -->
          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-pink-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Total Nilai Transaksi</p>
                <p class="text-lg font-bold text-gray-900">{{ pointStats.totalTransactionValueFormatted }}</p>
              </div>
              <div class="bg-pink-100 p-3 rounded-lg">
                <i class="fa-solid fa-money-bill-wave text-pink-600 text-xl"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Point Transactions & Distribution -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Latest Point Transactions -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-coins text-yellow-500"></i>
            Transaksi Point Terbaru
          </h3>
          <div class="space-y-3">
            <div v-if="pointTransactions.length === 0" class="text-center py-8 text-gray-500">
              <i class="fa-solid fa-coins text-4xl mb-4"></i>
              <p>Tidak ada transaksi point terbaru</p>
            </div>
            
            <div v-else class="space-y-3">
              <div
                v-for="transaction in pointTransactions"
                :key="transaction.id"
                class="bg-gray-50 rounded-lg p-4 hover:bg-gray-100 transition"
              >
                <div class="flex items-center justify-between mb-2">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="transaction.type === '1' ? 'bg-green-100' : 'bg-orange-100'">
                      <i :class="[transaction.icon, transaction.color]"></i>
                    </div>
                    <div>
                      <h4 class="font-semibold text-gray-900">{{ transaction.customer_name }}</h4>
                      <p class="text-sm text-gray-600">{{ transaction.customer_id }}</p>
                    </div>
                  </div>
                  <div class="text-right">
                    <span :class="['px-2 py-1 rounded-full text-xs font-medium', transaction.type === '1' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800']">
                      {{ transaction.type_text }}
                    </span>
                  </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <span class="text-gray-500">Point:</span>
                    <span class="ml-1 font-semibold text-gray-900">{{ transaction.point_formatted }}</span>
                  </div>
                  <div>
                    <span class="text-gray-500">Nilai:</span>
                    <span class="ml-1 font-semibold text-gray-900">{{ transaction.jml_trans_formatted }}</span>
                  </div>
                  <div>
                    <span class="text-gray-500">Cabang:</span>
                    <span class="ml-1 text-gray-900">{{ transaction.cabang_name }}</span>
                  </div>
                  <div>
                    <span class="text-gray-500">Bill:</span>
                    <span class="ml-1 text-gray-900">{{ transaction.bill_number }}</span>
                  </div>
                </div>
                
                <div class="mt-2 pt-2 border-t border-gray-200">
                  <div class="flex justify-between text-xs text-gray-500">
                    <span>{{ transaction.created_at }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Point Distribution by Cabang -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="flex justify-between items-center mb-4">
            <div>
              <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-blue-500"></i>
                Distribusi Point per Cabang
              </h3>
              <p class="text-sm text-gray-600 mt-1">
                Menampilkan total transaksi, point, dan nilai transaksi. 
                <span class="text-orange-600 font-medium">Bagian bawah menampilkan data redeem.</span>
              </p>
            </div>
          </div>
          
          <!-- Date Filter -->
          <div class="mb-6 p-4 bg-gray-50 rounded-lg">
            <div class="flex flex-col sm:flex-row gap-4 items-end">
              <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
                <input
                  v-model="dateFilters.start_date"
                  type="date"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
                <input
                  v-model="dateFilters.end_date"
                  type="date"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div class="flex gap-2">
                <button
                  @click="applyDateFilter"
                  class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition flex items-center gap-2"
                >
                  <i class="fa-solid fa-filter"></i>
                  Filter
                </button>
                <button
                  @click="clearDateFilter"
                  class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition flex items-center gap-2"
                >
                  <i class="fa-solid fa-times"></i>
                  Reset
                </button>
              </div>
            </div>
            <div v-if="filters.start_date || filters.end_date" class="mt-2 text-sm text-gray-600">
              <i class="fa-solid fa-info-circle"></i>
              Filter aktif: {{ filters.start_date || 'Semua' }} - {{ filters.end_date || 'Semua' }}
            </div>
          </div>
          
          <div class="space-y-3">
            <div v-if="pointByCabang.length === 0" class="text-center py-8 text-gray-500">
              <i class="fa-solid fa-chart-pie text-4xl mb-4"></i>
              <p>Tidak ada data distribusi cabang</p>
            </div>
            
            <div v-else class="space-y-3">
              <div
                v-for="(cabang, index) in pointByCabang"
                :key="index"
                class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
              >
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                    <span class="text-sm font-bold text-blue-600">{{ index + 1 }}</span>
                  </div>
                  <div>
                    <h4 class="font-semibold text-gray-900">{{ cabang.cabang_name }}</h4>
                    <p class="text-sm text-gray-600">{{ cabang.total_transactions }} transaksi</p>
                  </div>
                </div>
                <div class="text-right">
                  <div class="mb-1">
                    <p class="text-sm font-semibold text-gray-900">{{ cabang.total_points_formatted }} point</p>
                    <p class="text-xs text-gray-500">{{ cabang.total_value_formatted }}</p>
                  </div>
                  <div class="border-t border-gray-200 pt-1">
                    <div class="flex items-center justify-between mb-1">
                      <p class="text-xs text-orange-600 font-medium">{{ cabang.total_redeem }} redeem</p>
                      <button
                        v-if="cabang.total_redeem > 0"
                        @click="openRedeemModal(cabang.cabang_id, cabang.cabang_name)"
                        class="text-xs bg-orange-100 text-orange-700 px-2 py-1 rounded hover:bg-orange-200 transition-colors"
                        title="Lihat detail redeem"
                      >
                        <i class="fa-solid fa-eye mr-1"></i>
                        Detail
                      </button>
                    </div>
                    <p class="text-xs text-orange-500">{{ cabang.total_redeem_points_formatted }} point</p>
                    <p class="text-xs text-gray-400">{{ cabang.total_redeem_value_formatted }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Latest Members & Activity -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Latest Members -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
              <i class="fa-solid fa-user-plus text-orange-500"></i>
              Member Terbaru
            </h3>
            <button
              @click="goToMembers"
              class="text-sm text-purple-600 hover:text-purple-700 font-medium"
            >
              Lihat Semua
            </button>
          </div>
          <CrmLatestMembers :members="latestMembers" />
        </div>

        <!-- Activity List -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-clock text-indigo-500"></i>
            Aktivitas Terbaru
          </h3>
          <CrmActivityList :activities="memberActivity" />
        </div>
      </div>
    </div>

    <!-- Redeem Details Modal -->
    <RedeemDetailsModal
      :is-open="redeemModal.isOpen"
      :cabang-id="redeemModal.cabangId"
      :cabang-name="redeemModal.cabangName"
      :start-date="dateFilters.start_date"
      :end-date="dateFilters.end_date"
      @close="closeRedeemModal"
    />
  </AppLayout>
</template> 