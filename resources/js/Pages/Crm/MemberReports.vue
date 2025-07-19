<template>
  <AppLayout title="Member Reports">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        <i class="fa-solid fa-chart-line text-blue-600 mr-2"></i>
        Member Reports
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-filter text-gray-500"></i>
            Filter Data
          </h3>
          
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Date From -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-calendar text-gray-500 mr-1"></i>
                Tanggal Dari
              </label>
              <input
                v-model="filters.date_from"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @change="applyFilters"
              />
            </div>

            <!-- Date To -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-calendar text-gray-500 mr-1"></i>
                Tanggal Sampai
              </label>
              <input
                v-model="filters.date_to"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @change="applyFilters"
              />
            </div>

            <!-- Reset Button -->
            <div class="flex items-end">
              <button
                @click="resetFilters"
                class="w-full px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
              >
                <i class="fa-solid fa-rotate-left mr-2"></i>
                Reset Filter
              </button>
            </div>
          </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <!-- Total Members -->
          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Total Member</p>
                <p class="text-2xl font-bold text-gray-900">{{ formatNumber(reportData.summary?.total_members || 0) }}</p>
              </div>
              <div class="bg-blue-100 p-3 rounded-lg">
                <i class="fa-solid fa-users text-blue-600 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- Total Transactions -->
          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Total Transaksi</p>
                <p class="text-2xl font-bold text-gray-900">{{ formatNumber(reportData.summary?.total_transactions || 0) }}</p>
              </div>
              <div class="bg-green-100 p-3 rounded-lg">
                <i class="fa-solid fa-receipt text-green-600 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- Total Value -->
          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Total Nilai Transaksi</p>
                <p class="text-lg font-bold text-gray-900">{{ reportData.summary?.total_value_formatted || 'Rp 0' }}</p>
              </div>
              <div class="bg-purple-100 p-3 rounded-lg">
                <i class="fa-solid fa-money-bill-wave text-purple-600 text-xl"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Top 20 Members by Transaction Value -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-trophy text-yellow-500"></i>
            Top 20 Member dengan Nilai Transaksi Terbesar
          </h3>
          
          <div v-if="reportData.top_by_value?.length === 0" class="text-center py-8 text-gray-500">
            <i class="fa-solid fa-chart-bar text-4xl mb-4"></i>
            <p>Tidak ada data untuk periode ini</p>
          </div>
          
          <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-hashtag mr-1"></i>
                    Rank
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-user mr-1"></i>
                    Member
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-phone mr-1"></i>
                    Telepon
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-envelope mr-1"></i>
                    Email
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-receipt mr-1"></i>
                    Jumlah Transaksi
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-money-bill-wave mr-1"></i>
                    Total Nilai
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-chart-line mr-1"></i>
                    Rata-rata/Transaksi
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="(member, index) in reportData.top_by_value" :key="member.customer_id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <span v-if="index < 3" class="text-lg font-bold" :class="getRankColor(index + 1)">
                        {{ index + 1 }}
                      </span>
                      <span v-else class="text-sm font-medium text-gray-900">
                        {{ index + 1 }}
                      </span>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-10 w-10">
                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                          <i class="fa-solid fa-user text-blue-600"></i>
                        </div>
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">{{ member.customer_name }}</div>
                        <div class="text-sm text-gray-500">ID: {{ member.customer_id }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ member.phone || '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ member.email || '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                      {{ formatNumber(member.transaction_count) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                    {{ formatRupiah(member.total_value) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                    {{ formatRupiah(member.average_value) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Most Frequent Shoppers -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-shopping-cart text-green-500"></i>
            Top 20 Member Paling Sering Berbelanja
          </h3>
          
          <div v-if="reportData.top_by_frequency?.length === 0" class="text-center py-8 text-gray-500">
            <i class="fa-solid fa-shopping-bag text-4xl mb-4"></i>
            <p>Tidak ada data untuk periode ini</p>
          </div>
          
          <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-hashtag mr-1"></i>
                    Rank
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-user mr-1"></i>
                    Member
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-phone mr-1"></i>
                    Telepon
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-envelope mr-1"></i>
                    Email
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-receipt mr-1"></i>
                    Jumlah Transaksi
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-money-bill-wave mr-1"></i>
                    Total Nilai
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-calendar mr-1"></i>
                    Frekuensi/Hari
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="(member, index) in reportData.top_by_frequency" :key="member.customer_id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <span v-if="index < 3" class="text-lg font-bold" :class="getRankColor(index + 1)">
                        {{ index + 1 }}
                      </span>
                      <span v-else class="text-sm font-medium text-gray-900">
                        {{ index + 1 }}
                      </span>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-10 w-10">
                        <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                          <i class="fa-solid fa-user text-green-600"></i>
                        </div>
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">{{ member.customer_name }}</div>
                        <div class="text-sm text-gray-500">ID: {{ member.customer_id }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ member.phone || '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ member.email || '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                      {{ formatNumber(member.transaction_count) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                    {{ formatRupiah(member.total_value) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                    {{ formatNumber(member.frequency_per_day, 2) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  reportData: {
    type: Object,
    default: () => ({
      summary: {},
      top_by_value: [],
      top_by_frequency: []
    })
  },
  filters: {
    type: Object,
    default: () => ({})
  }
});

// Reactive data
const filters = ref({
  date_from: props.filters.date_from || getDefaultDateFrom(),
  date_to: props.filters.date_to || getDefaultDateTo(),
});

// Utility functions
function getDefaultDateFrom() {
  const date = new Date();
  date.setMonth(date.getMonth() - 1);
  return date.toISOString().split('T')[0];
}

function getDefaultDateTo() {
  return new Date().toISOString().split('T')[0];
}

function formatNumber(value, decimals = 0) {
  if (value === null || value === undefined) return '0';
  return new Intl.NumberFormat('id-ID').format(Number(value).toFixed(decimals));
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

function getRankColor(rank) {
  switch (rank) {
    case 1: return 'text-yellow-600';
    case 2: return 'text-gray-500';
    case 3: return 'text-orange-600';
    default: return 'text-gray-900';
  }
}

function applyFilters() {
  router.visit('/crm/member-reports', {
    data: filters.value,
    preserveState: true,
    replace: true,
  });
}

function resetFilters() {
  filters.value = {
    date_from: getDefaultDateFrom(),
    date_to: getDefaultDateTo(),
  };
  applyFilters();
}

onMounted(() => {
  // Apply default filters if none provided
  if (!props.filters.date_from && !props.filters.date_to) {
    applyFilters();
  }
});
</script> 