<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-calendar-check text-pink-500"></i> Reservasi
        </h1>
        <Link
          :href="route('reservations.create')"
          class="bg-gradient-to-r from-pink-500 to-pink-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
        >
          + Buat Reservasi
        </Link>
      </div>
      <!-- Search & Filter -->
      <div class="flex flex-col md:flex-row gap-4 mb-6">
        <div class="flex-1">
          <input
            v-model="search"
            type="text"
            placeholder="Cari nama reservasi..."
            class="w-full rounded-lg border-gray-300"
          />
        </div>
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex items-center gap-2">
            <input
              type="date"
              v-model="dateFrom"
              class="rounded-lg border-gray-300"
            />
            <span class="text-gray-500">sampai</span>
            <input
              type="date"
              v-model="dateTo"
              class="rounded-lg border-gray-300"
            />
          </div>
          <select v-model="status" class="rounded-lg border-gray-300">
            <option value="">Semua Status</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <button
            @click="applyFilters"
            class="px-4 py-2 bg-pink-600 text-white rounded-lg hover:bg-pink-700"
          >
            Filter
          </button>
          <button
            @click="resetFilters"
            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
          >
            Reset
          </button>
        </div>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-pink-50 to-pink-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider rounded-tl-2xl">No</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider">Nama</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider">Waktu</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider">Jumlah Tamu</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider">Area</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider">Created By</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!reservations.length">
              <td colspan="10" class="text-center py-10 text-pink-300">Tidak ada data Reservasi.</td>
            </tr>
            <tr v-for="(reservation, idx) in reservations" :key="reservation.id" class="hover:bg-pink-50 transition shadow-sm">
              <td class="px-6 py-3">{{ idx + 1 }}</td>
              <td class="px-6 py-3">{{ reservation.name }}</td>
              <td class="px-6 py-3">{{ reservation.outlet }}</td>
              <td class="px-6 py-3">{{ formatDate(reservation.reservation_date) }}</td>
              <td class="px-6 py-3">{{ formatTime(reservation.reservation_time) }}</td>
              <td class="px-6 py-3">{{ reservation.number_of_guests }} orang</td>
              <td class="px-6 py-3">
                <span :class="[
                  'px-2 py-1 rounded-full text-xs font-medium',
                  reservation.smoking_preference === 'smoking' 
                    ? 'bg-yellow-100 text-yellow-800' 
                    : 'bg-green-100 text-green-800'
                ]">
                  {{ reservation.smoking_preference === 'smoking' ? 'Smoking' : 'Non-Smoking' }}
                </span>
              </td>
              <td class="px-6 py-3">
                <span :class="getStatusClass(reservation.status)" class="px-2 py-1 rounded-full text-xs font-bold">
                  {{ getStatusText(reservation.status) }}
                </span>
              </td>
              <td class="px-6 py-3">{{ reservation.created_by }}</td>
              <td class="px-6 py-3">
                <Link :href="route('reservations.show', { reservation: reservation.id })" class="inline-flex items-center btn btn-xs bg-pink-100 text-pink-800 hover:bg-pink-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-eye mr-1"></i> Detail
                </Link>
                <Link :href="route('reservations.edit', { reservation: reservation.id })" class="ml-2 inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-edit mr-1"></i> Edit
                </Link>
                <button @click="handleDelete(reservation.id)" :disabled="loadingDeleteId === reservation.id" class="ml-2 inline-flex items-center btn btn-xs bg-red-100 text-red-800 hover:bg-red-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50">
                  <i v-if="loadingDeleteId === reservation.id" class="fa fa-spinner fa-spin mr-1"></i>
                  <i v-else class="fa fa-trash mr-1"></i> Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { ref } from 'vue';

const props = defineProps({
  reservations: {
    type: Array,
    required: true,
    default: () => []
  },
  search: String,
  status: String,
  dateFrom: String,
  dateTo: String
});

const search = ref(props.search || '');
const status = ref(props.status || '');
const dateFrom = ref(props.dateFrom || '');
const dateTo = ref(props.dateTo || '');
const loadingDeleteId = ref(null);

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
}

function formatTime(time) {
  if (!time) return '-';
  return new Date(time).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function getStatusClass(status) {
  switch (status) {
    case 'confirmed':
      return 'bg-green-100 text-green-800';
    case 'cancelled':
      return 'bg-red-100 text-red-800';
    default:
      return 'bg-yellow-100 text-yellow-800';
  }
}

function getStatusText(status) {
  switch (status) {
    case 'confirmed':
      return 'Confirmed';
    case 'cancelled':
      return 'Cancelled';
    default:
      return 'Pending';
  }
}

async function handleDelete(id) {
  const confirm = await Swal.fire({
    title: 'Hapus Reservasi?',
    text: 'Data reservasi akan dihapus. Lanjutkan?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;
  loadingDeleteId.value = id;
  router.delete(route('reservations.destroy', { reservation: id }), {
    onSuccess: () => {
      loadingDeleteId.value = null;
      Swal.fire('Sukses', 'Reservasi berhasil dihapus!', 'success');
    },
    onError: () => {
      loadingDeleteId.value = null;
      Swal.fire('Error', 'Gagal menghapus reservasi', 'error');
    }
  });
}

function applyFilters() {
  router.get(
    route('reservations.index'),
    {
      search: search.value,
      status: status.value,
      dateFrom: dateFrom.value,
      dateTo: dateTo.value
    },
    {
      preserveState: true,
      preserveScroll: true
    }
  );
}

function resetFilters() {
  search.value = '';
  status.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  applyFilters();
}
</script> 