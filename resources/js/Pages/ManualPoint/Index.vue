<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-coins"></i> Inject Point Manual
        </h1>
        <Link
          href="/manual-point/create"
          class="px-4 py-2 bg-gradient-to-r from-green-500 to-green-700 text-white rounded-lg font-semibold hover:from-green-600 hover:to-green-800 transition-all flex items-center gap-2"
        >
          <i class="fa-solid fa-plus"></i> Inject Point Baru
        </Link>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-blue-500">
          <div class="text-sm text-gray-600 mb-1">Total Injections</div>
          <div class="text-2xl font-bold text-gray-800">{{ stats.total_injections }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-green-500">
          <div class="text-sm text-gray-600 mb-1">Total Points Injected</div>
          <div class="text-2xl font-bold text-green-600">{{ formatNumber(stats.total_points_injected) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-purple-500">
          <div class="text-sm text-gray-600 mb-1">Injections Hari Ini</div>
          <div class="text-2xl font-bold text-purple-600">{{ stats.today_injections }}</div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="flex gap-4 items-end">
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-2">Cari</label>
            <input
              type="text"
              v-model="filters.search"
              @input="onSearchInput"
              placeholder="Cari member ID, nama, email, atau reference ID..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-2">Dari Tanggal</label>
            <input
              type="date"
              v-model="filters.date_from"
              @change="applyFilters"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-2">Sampai Tanggal</label>
            <input
              type="date"
              v-model="filters.date_to"
              @change="applyFilters"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <button
              @click="resetFilters"
              class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition"
            >
              Reset
            </button>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-green-500 to-green-700 text-white">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">ID</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Member</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Point Amount</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Reference ID</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Keterangan</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Tanggal Transaksi</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Expiry Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="transaction in transactions.data" :key="transaction.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  #{{ transaction.id }}
                </td>
                <td class="px-6 py-4 text-sm">
                  <div class="font-medium text-gray-900">{{ transaction.member?.nama_lengkap || '-' }}</div>
                  <div class="text-gray-500 text-xs">{{ transaction.member?.member_id || '-' }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                    +{{ formatNumber(transaction.point_amount) }} points
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">
                  {{ transaction.reference_id || '-' }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate" :title="transaction.description">
                  {{ transaction.description }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDate(transaction.transaction_date) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDate(transaction.expires_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <Link
                    :href="`/manual-point/${transaction.id}`"
                    class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition"
                  >
                    <i class="fa-solid fa-eye mr-1"></i> Detail
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-if="transactions.data.length === 0" class="text-center py-12">
          <i class="fa-solid fa-coins text-6xl text-gray-300 mb-4"></i>
          <p class="text-gray-500 text-lg">Belum ada point injection</p>
          <Link
            href="/manual-point/create"
            class="mt-4 inline-block px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition"
          >
            Inject Point Pertama
          </Link>
        </div>

        <!-- Pagination -->
        <div v-if="transactions.data.length > 0" class="px-6 py-4 border-t border-gray-200">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Menampilkan {{ transactions.from }} sampai {{ transactions.to }} dari {{ transactions.total }} injection
            </div>
            <div class="flex gap-2">
              <Link
                v-if="transactions.prev_page_url"
                :href="transactions.prev_page_url"
                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition"
              >
                <i class="fa-solid fa-chevron-left mr-1"></i> Sebelumnya
              </Link>
              <Link
                v-if="transactions.next_page_url"
                :href="transactions.next_page_url"
                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition"
              >
                Selanjutnya <i class="fa-solid fa-chevron-right ml-1"></i>
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  transactions: Object,
  stats: Object,
  filters: Object,
});

const filters = ref({
  search: props.filters?.search || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
});

let searchTimeout = null;

const onSearchInput = () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  
  searchTimeout = setTimeout(() => {
    applyFilters();
  }, 500);
};

const applyFilters = () => {
  router.get('/manual-point', filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};

const resetFilters = () => {
  filters.value = {
    search: '',
    date_from: '',
    date_to: '',
  };
  applyFilters();
};

const formatDate = (date) => {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const formatNumber = (num) => {
  if (!num) return '0';
  return new Intl.NumberFormat('id-ID').format(num);
};
</script>

