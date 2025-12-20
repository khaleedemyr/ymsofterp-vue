<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-bell"></i> Daftar Notifikasi Member
        </h1>
        <Link
          href="/member-notification/create"
          class="px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-700 text-white rounded-lg font-semibold hover:from-blue-600 hover:to-blue-800 transition-all flex items-center gap-2"
        >
          <i class="fa-solid fa-plus"></i> Kirim Notifikasi Baru
        </Link>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-blue-500">
          <div class="text-sm text-gray-600 mb-1">Total Notifikasi</div>
          <div class="text-2xl font-bold text-gray-800">{{ stats.total_notifications }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-green-500">
          <div class="text-sm text-gray-600 mb-1">Sudah Dikirim</div>
          <div class="text-2xl font-bold text-green-600">{{ stats.total_sent }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-purple-500">
          <div class="text-sm text-gray-600 mb-1">Total Penerima</div>
          <div class="text-2xl font-bold text-purple-600">{{ stats.total_recipients }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-yellow-500">
          <div class="text-sm text-gray-600 mb-1">Sudah Dibaca</div>
          <div class="text-2xl font-bold text-yellow-600">{{ stats.total_opened }}</div>
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
              placeholder="Cari judul atau pesan notifikasi..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-2">Target Type</label>
            <select
              v-model="filters.target_type"
              @change="applyFilters"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua</option>
              <option value="all">Semua Member</option>
              <option value="specific">Pilih Member</option>
              <option value="filter">Filter Member</option>
            </select>
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
            <thead class="bg-gradient-to-r from-blue-500 to-blue-700 text-white">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">ID</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Judul</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Pesan</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Target</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Total Penerima</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Terkirim</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Dibaca</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="notification in notifications.data" :key="notification.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  #{{ notification.id }}
                </td>
                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                  {{ notification.title }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate">
                  {{ notification.message }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 text-xs font-semibold rounded-full"
                    :class="{
                      'bg-blue-100 text-blue-800': notification.target_type === 'all',
                      'bg-green-100 text-green-800': notification.target_type === 'specific',
                      'bg-purple-100 text-purple-800': notification.target_type === 'filter'
                    }">
                    {{ getTargetTypeLabel(notification.target_type) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ notification.total_recipients || 0 }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-semibold">
                  {{ notification.sent_count || 0 }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 font-semibold">
                  {{ notification.opened_count || 0 }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDate(notification.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <Link
                    :href="`/member-notification/${notification.id}`"
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
        <div v-if="notifications.data.length === 0" class="text-center py-12">
          <i class="fa-solid fa-bell-slash text-6xl text-gray-300 mb-4"></i>
          <p class="text-gray-500 text-lg">Belum ada notifikasi yang dikirim</p>
          <Link
            href="/member-notification/create"
            class="mt-4 inline-block px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition"
          >
            Kirim Notifikasi Pertama
          </Link>
        </div>

        <!-- Pagination -->
        <div v-if="notifications.data.length > 0" class="px-6 py-4 border-t border-gray-200">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Menampilkan {{ notifications.from }} sampai {{ notifications.to }} dari {{ notifications.total }} notifikasi
            </div>
            <div class="flex gap-2">
              <Link
                v-if="notifications.prev_page_url"
                :href="notifications.prev_page_url"
                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition"
              >
                <i class="fa-solid fa-chevron-left mr-1"></i> Sebelumnya
              </Link>
              <Link
                v-if="notifications.next_page_url"
                :href="notifications.next_page_url"
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
  notifications: Object,
  stats: Object,
  filters: Object,
});

const filters = ref({
  search: props.filters?.search || '',
  target_type: props.filters?.target_type || '',
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
  router.get('/member-notification', filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};

const resetFilters = () => {
  filters.value = {
    search: '',
    target_type: '',
  };
  applyFilters();
};

const getTargetTypeLabel = (type) => {
  const labels = {
    'all': 'Semua Member',
    'specific': 'Pilih Member',
    'filter': 'Filter Member',
  };
  return labels[type] || type;
};

const formatDate = (date) => {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};
</script>

