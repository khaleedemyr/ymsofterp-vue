<template>
  <AppLayout title="Push Notification">
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-bell text-purple-500"></i> Push Notification
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-purple-500 to-purple-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Notifikasi Baru
        </button>
      </div>

      <!-- Success Message -->
      <div v-if="$page.props.flash?.success" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
        <i class="fa fa-check-circle"></i>
        {{ $page.props.flash.success }}
      </div>

      <!-- Error Message -->
      <div v-if="$page.props.flash?.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4 flex items-center gap-2">
        <i class="fa fa-exclamation-circle"></i>
        {{ $page.props.flash.error }}
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Total Devices -->
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-blue-500">
          <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-lg">
              <i class="fa-solid fa-mobile-screen text-blue-600 text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Total Devices</p>
              <p class="text-2xl font-bold text-gray-800">{{ formatNumber(totalDevices) }}</p>
            </div>
          </div>
        </div>

        <!-- Total Notifikasi -->
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-purple-500">
          <div class="flex items-center">
            <div class="p-2 bg-purple-100 rounded-lg">
              <i class="fa-solid fa-bell text-purple-600 text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Total Notifikasi</p>
              <p class="text-2xl font-bold text-gray-800">{{ formatNumber(stats?.total_notifications || 0) }}</p>
            </div>
          </div>
        </div>

        <!-- Notifikasi Terkirim -->
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-green-500">
          <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-lg">
              <i class="fa-solid fa-check-circle text-green-600 text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Terkirim</p>
              <p class="text-2xl font-bold text-gray-800">{{ formatNumber(stats?.sent_notifications || 0) }}</p>
            </div>
          </div>
        </div>

        <!-- Notifikasi Pending -->
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-orange-500">
          <div class="flex items-center">
            <div class="p-2 bg-orange-100 rounded-lg">
              <i class="fa-solid fa-clock text-orange-600 text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Pending</p>
              <p class="text-2xl font-bold text-gray-800">{{ formatNumber(stats?.pending_notifications || 0) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="mb-4 flex gap-4 flex-wrap">
        <select v-model="statusFilter" class="px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400">
          <option value="">Semua Status</option>
          <option value="sent">Terkirim</option>
          <option value="pending">Belum Terkirim</option>
          <option value="processing">Sedang Diproses</option>
        </select>

        <select v-model="targetTypeFilter" class="px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400">
          <option value="">Semua Target</option>
          <option value="all">All Devices</option>
          <option value="specific">Target Spesifik</option>
        </select>

        <select v-model="perPageFilter" class="px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400">
          <option value="10">10 Data</option>
          <option value="15">15 Data</option>
          <option value="25">25 Data</option>
          <option value="50">50 Data</option>
          <option value="100">100 Data</option>
        </select>

        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari ID, Title, Body, Target..."
          class="flex-1 px-4 py-2 rounded-xl border border-purple-200 shadow focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"
        />
      </div>

      <!-- Notifications Table -->
      <div class="bg-white rounded-2xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-purple-200">
          <thead class="bg-purple-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">ID</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Title</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Body</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Target</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Image</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Report</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="notification in notifications.data" :key="notification.id" class="hover:bg-purple-50 transition">
              <td class="px-4 py-2 whitespace-nowrap font-mono text-sm">{{ notification.id }}</td>
              <td class="px-4 py-2 whitespace-nowrap font-semibold">{{ notification.title }}</td>
              <td class="px-4 py-2">{{ truncateText(notification.body, 50) }}</td>
              <td class="px-4 py-2 whitespace-nowrap">
                <span v-if="notification.target === 'all'" class="px-2 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
                  All Devices
                </span>
                <span v-else class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">
                  {{ truncateText(notification.target, 30) }}
                </span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <div class="flex items-center">
                  <div :class="[
                    'w-3 h-3 rounded-full mr-2',
                    notification.status_send === 1 ? 'bg-green-500' : notification.status_send === 2 ? 'bg-blue-500' : 'bg-orange-500'
                  ]"></div>
                  <span :class="[
                    'text-sm font-medium',
                    notification.status_send === 1 ? 'text-green-700' : notification.status_send === 2 ? 'text-blue-700' : 'text-orange-700'
                  ]">
                    {{ getStatusText(notification.status_send) }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm">
                <a
                  v-if="notification.photo"
                  :href="getImageUrl(notification.photo)"
                  target="_blank"
                  class="text-purple-600 hover:text-purple-800 font-medium"
                >
                  <i class="fa fa-image mr-1"></i>
                  [image]
                </a>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                <span v-if="notification.target === 'all' || notification.target === ''">
                  {{ notification.status_send == 1 ? 'Finish' : 'On Process' }}:
                  Terkirim ke {{ notification.sended_devices || 0 }} perangkat
                </span>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center">
                <button
                  v-if="notification.status_send == 0"
                  @click="sendNotification(notification.id)"
                  class="px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 transition"
                  title="Kirim Notifikasi"
                >
                  <i class="fa fa-paper-plane"></i>
                </button>
                <span v-else class="text-gray-400">-</span>
              </td>
            </tr>
            <tr v-if="!notifications.data || notifications.data.length === 0">
              <td colspan="8" class="text-center py-8 text-gray-400">Tidak ada data notifikasi</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Info & Controls -->
      <div class="mt-4 flex justify-between items-center">
        <!-- Data Info -->
        <div class="text-sm text-gray-600">
          Menampilkan {{ notifications.from || 0 }} - {{ notifications.to || 0 }} dari {{ notifications.total || 0 }} data notifikasi
        </div>
        
        <!-- Pagination -->
        <nav v-if="notifications.links && notifications.links.length > 3" class="inline-flex -space-x-px">
          <template v-for="(link, i) in notifications.links" :key="i">
            <button v-if="link.url" @click="goToPage(link.url)" :class="['px-3 py-1 border border-gray-300', link.active ? 'bg-purple-600 text-white' : 'bg-white text-purple-700 hover:bg-purple-50']" v-html="link.label"></button>
            <span v-else class="px-3 py-1 border border-gray-200 text-gray-400" v-html="link.label"></span>
          </template>
        </nav>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  notifications: Object, // { data, links, meta }
  totalDevices: Number,
  stats: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const targetTypeFilter = ref(props.filters?.target_type || '');
const perPageFilter = ref(props.filters?.per_page || '15');

const debouncedSearch = debounce(() => {
  router.get('/push-notification', {
    search: search.value,
    status: statusFilter.value,
    target_type: targetTypeFilter.value,
    per_page: perPageFilter.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function changeSort(sortField) {
  const currentSort = props.filters?.sort || 'id';
  const currentDirection = props.filters?.direction || 'desc';
  
  let newDirection = 'desc';
  if (currentSort === sortField) {
    newDirection = currentDirection === 'desc' ? 'asc' : 'desc';
  }
  
  router.get('/push-notification', {
    search: search.value,
    status: statusFilter.value,
    target_type: targetTypeFilter.value,
    per_page: perPageFilter.value,
    sort: sortField,
    direction: newDirection,
  }, { preserveState: true, replace: true });
}

watch([statusFilter, targetTypeFilter, perPageFilter], () => {
  router.get('/push-notification', {
    search: search.value,
    status: statusFilter.value,
    target_type: targetTypeFilter.value,
    per_page: perPageFilter.value,
  }, { preserveState: true, replace: true });
});

const todaySentCount = computed(() => {
  const today = new Date().toDateString();
  if (!props.notifications?.data) return 0;
  return props.notifications.data.filter(n => {
    const notifDate = new Date(n.created_at).toDateString();
    return notifDate === today && n.status_send == 1;
  }).length;
});

function openCreate() {
  router.visit('/push-notification/create');
}

function truncateText(text, length) {
  if (!text) return '';
  return text.length > length ? text.substring(0, length) + '...' : text;
}

function formatNumber(number) {
  return number.toLocaleString('id-ID');
}

function getStatusText(status) {
  const statusMessages = {
    0: 'belum terkirim',
    1: 'terkirim',
    2: 'sedang di proses',
  };
  return statusMessages[status] || 'status tidak diketahui';
}

function getImageUrl(photo) {
  return `/assets/file_photo_notification/${photo}`;
}

function sendNotification(id) {
  Swal.fire({
    title: 'Kirim Notifikasi?',
    text: 'Yakin ingin mengirim notifikasi ini ke semua target?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya, Kirim',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.post(`/push-notification/${id}/send`, {}, {
        onSuccess: () => {
          Swal.fire('Berhasil', 'Notifikasi berhasil dikirim!', 'success');
        },
        onError: (errors) => {
          Swal.fire('Error', errors.error || 'Gagal mengirim notifikasi', 'error');
        },
      });
    }
  });
}
</script>


