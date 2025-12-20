<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <div>
          <Link
            href="/member-notification"
            class="text-blue-600 hover:text-blue-800 mb-2 inline-flex items-center gap-2"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar
          </Link>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-bell"></i> Detail Notifikasi
          </h1>
        </div>
      </div>

      <!-- Notification Info -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Informasi Notifikasi</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-gray-600">Judul</label>
            <p class="text-lg font-semibold text-gray-800">{{ notification.title }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">Target Type</label>
            <p class="text-sm">
              <span class="px-2 py-1 text-xs font-semibold rounded-full"
                :class="{
                  'bg-blue-100 text-blue-800': notification.target_type === 'all',
                  'bg-green-100 text-green-800': notification.target_type === 'specific',
                  'bg-purple-100 text-purple-800': notification.target_type === 'filter'
                }">
                {{ getTargetTypeLabel(notification.target_type) }}
              </span>
            </p>
          </div>
          <div class="md:col-span-2">
            <label class="text-sm font-medium text-gray-600">Pesan</label>
            <p class="text-gray-800 whitespace-pre-wrap">{{ notification.message }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">Tanggal Dikirim</label>
            <p class="text-gray-800">{{ formatDate(notification.sent_at || notification.created_at) }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">Dibuat Oleh</label>
            <p class="text-gray-800">{{ notification.created_by ? `User ID: ${notification.created_by}` : '-' }}</p>
          </div>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-blue-500">
          <div class="text-sm text-gray-600 mb-1">Total Penerima</div>
          <div class="text-2xl font-bold text-gray-800">{{ stats.total_recipients }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-green-500">
          <div class="text-sm text-gray-600 mb-1">Terkirim</div>
          <div class="text-2xl font-bold text-green-600">{{ stats.sent_count }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-purple-500">
          <div class="text-sm text-gray-600 mb-1">Terkirim & Terbaca</div>
          <div class="text-2xl font-bold text-purple-600">{{ stats.delivered_count }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-yellow-500">
          <div class="text-sm text-gray-600 mb-1">Sudah Dibaca</div>
          <div class="text-2xl font-bold text-yellow-600">{{ stats.opened_count }}</div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="flex gap-4 items-end">
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-2">Cari Penerima</label>
            <input
              type="text"
              v-model="filters.search"
              @input="onSearchInput"
              placeholder="Cari nama, email, atau ID member..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div class="w-48">
            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select
              v-model="filters.status"
              @change="applyFilters"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Status</option>
              <option value="sent">Terkirim</option>
              <option value="delivered">Terkirim & Terbaca</option>
              <option value="opened">Sudah Dibaca</option>
              <option value="failed">Gagal</option>
              <option value="pending">Pending</option>
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

      <!-- Recipients Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-bold text-gray-800">Daftar Penerima</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-500 to-blue-700 text-white">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Member ID</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Level</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Device Type</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Dibaca</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Error</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="recipient in recipients.data" :key="recipient.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ recipient.member?.member_id || '-' }}
                </td>
                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                  {{ recipient.member?.nama_lengkap || '-' }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">
                  {{ recipient.member?.email || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                    {{ recipient.member?.member_level || '-' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                  <span v-if="recipient.device_token" class="px-2 py-1 text-xs font-semibold rounded-full"
                    :class="{
                      'bg-green-100 text-green-800': recipient.device_token.device_type === 'android',
                      'bg-blue-100 text-blue-800': recipient.device_token.device_type === 'ios',
                      'bg-purple-100 text-purple-800': recipient.device_token.device_type === 'web'
                    }">
                    {{ recipient.device_token.device_type?.toUpperCase() || '-' }}
                  </span>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 text-xs font-semibold rounded-full"
                    :class="{
                      'bg-green-100 text-green-800': recipient.status === 'sent' || recipient.status === 'delivered',
                      'bg-blue-100 text-blue-800': recipient.status === 'opened',
                      'bg-red-100 text-red-800': recipient.status === 'failed',
                      'bg-yellow-100 text-yellow-800': recipient.status === 'pending'
                    }">
                    {{ getStatusLabel(recipient.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <span v-if="recipient.is_read || recipient.opened_at" class="text-green-600 font-semibold">
                    <i class="fa-solid fa-check-circle mr-1"></i>
                    {{ formatDate(recipient.opened_at || recipient.read_at) }}
                  </span>
                  <span v-else class="text-gray-400">Belum dibaca</span>
                </td>
                <td class="px-6 py-4 text-sm text-red-600 max-w-xs truncate" :title="recipient.error_message">
                  {{ recipient.error_message || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-if="recipients.data.length === 0" class="text-center py-12">
          <i class="fa-solid fa-users-slash text-6xl text-gray-300 mb-4"></i>
          <p class="text-gray-500 text-lg">Tidak ada penerima yang ditemukan</p>
        </div>

        <!-- Pagination -->
        <div v-if="recipients.data.length > 0" class="px-6 py-4 border-t border-gray-200">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Menampilkan {{ recipients.from }} sampai {{ recipients.to }} dari {{ recipients.total }} penerima
            </div>
            <div class="flex gap-2">
              <Link
                v-if="recipients.prev_page_url"
                :href="recipients.prev_page_url"
                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition"
              >
                <i class="fa-solid fa-chevron-left mr-1"></i> Sebelumnya
              </Link>
              <Link
                v-if="recipients.next_page_url"
                :href="recipients.next_page_url"
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
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  notification: Object,
  recipients: Object,
  stats: Object,
  filters: Object,
});

const filters = ref({
  search: props.filters?.search || '',
  status: props.filters?.status || '',
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
  router.get(`/member-notification/${props.notification.id}`, filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};

const resetFilters = () => {
  filters.value = {
    search: '',
    status: '',
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

const getStatusLabel = (status) => {
  const labels = {
    'pending': 'Pending',
    'sent': 'Terkirim',
    'delivered': 'Terkirim & Terbaca',
    'opened': 'Sudah Dibaca',
    'failed': 'Gagal',
  };
  return labels[status] || status;
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

