<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold text-pink-700 mb-6">Detail Reservasi</h1>
      <div class="bg-white rounded-2xl shadow-xl p-8 space-y-4">
        <!-- Data Pemesan -->
        <div class="space-y-4">
          <h2 class="text-lg font-semibold text-gray-800">Data Pemesan</h2>
          <div><b>Nama:</b> {{ reservation.name }}</div>
          <div><b>Telepon:</b> {{ reservation.phone }}</div>
          <div><b>Email:</b> {{ reservation.email }}</div>
        </div>

        <!-- Detail Reservasi -->
        <div class="space-y-4">
          <h2 class="text-lg font-semibold text-gray-800">Detail Reservasi</h2>
          <div><b>Outlet:</b> {{ reservation.outlet?.nama_outlet }}</div>
          <div><b>Tanggal:</b> {{ formatDate(reservation.reservation_date) }}</div>
          <div><b>Waktu:</b> {{ formatTime(reservation.reservation_time) }}</div>
          <div><b>Created By:</b> {{ reservation.creator?.name || '-' }}</div>
          <div class="mb-4">
            <div class="text-sm font-medium text-gray-500">Jumlah Tamu</div>
            <div class="mt-1">{{ reservation.number_of_guests }} orang</div>
          </div>
          <div class="mb-4">
            <div class="text-sm font-medium text-gray-500">Preferensi Merokok</div>
            <div class="mt-1">
              <span :class="[
                'px-2 py-1 rounded-full text-xs font-medium',
                reservation.smoking_preference === 'smoking' 
                  ? 'bg-yellow-100 text-yellow-800' 
                  : 'bg-green-100 text-green-800'
              ]">
                {{ reservation.smoking_preference === 'smoking' ? 'Smoking Area' : 'Non-Smoking Area' }}
              </span>
            </div>
          </div>
          <div class="mb-4">
            <div class="text-sm font-medium text-gray-500">Catatan Khusus</div>
            <div class="mt-1">{{ reservation.special_requests || '-' }}</div>
          </div>
          <div>
            <b>Status:</b>
            <span :class="getStatusClass(reservation.status)" class="ml-2 px-2 py-1 rounded-full text-xs font-bold">
              {{ getStatusText(reservation.status) }}
            </span>
          </div>
        </div>

        <div class="flex justify-end mt-8">
          <Link :href="route('reservations.index')" class="px-4 py-2 rounded bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200">Kembali</Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  reservation: Object
});

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
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
      return 'Dikonfirmasi';
    case 'cancelled':
      return 'Dibatalkan';
    default:
      return 'Pending';
  }
}
</script> 