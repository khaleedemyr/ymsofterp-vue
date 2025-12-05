<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold text-pink-700 mb-6">{{ isEdit ? 'Edit Reservasi' : 'Tambah Reservasi' }}</h1>
      <form @submit.prevent="submit" class="space-y-5 bg-white rounded-2xl shadow-xl p-8">
        <!-- Data Pemesan -->
        <div class="space-y-4">
          <h2 class="text-lg font-semibold text-gray-800">Data Pemesan</h2>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
            <input v-model="form.name" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500" required maxlength="100" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nomor Telepon</label>
            <input v-model="form.phone" type="tel" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500" required maxlength="20" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Email</label>
            <input v-model="form.email" type="email" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500" required maxlength="100" />
          </div>
        </div>

        <!-- Detail Reservasi -->
        <div class="space-y-4">
          <h2 class="text-lg font-semibold text-gray-800">Detail Reservasi</h2>
          <div>
            <label class="block text-sm font-medium text-gray-700">Outlet</label>
            <select v-model="form.outlet_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500" required>
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
            </select>
          </div>
          <div class="flex gap-4">
            <div class="flex-1">
              <label class="block text-sm font-medium text-gray-700">Tanggal Reservasi</label>
              <input v-model="form.reservation_date" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500" required />
            </div>
            <div class="flex-1">
              <label class="block text-sm font-medium text-gray-700">Waktu Reservasi</label>
              <VueTimepicker v-model="form.reservation_time" format="HH:mm" :is24="true" minute-interval="15" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Tamu</label>
            <input type="number" v-model="form.number_of_guests" class="w-full rounded-lg border-gray-300" min="1" required />
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Preferensi Merokok</label>
            <select v-model="form.smoking_preference" class="w-full rounded-lg border-gray-300" required>
              <option value="">Pilih Preferensi</option>
              <option value="smoking">Smoking Area</option>
              <option value="non_smoking">Non-Smoking Area</option>
            </select>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Khusus</label>
            <textarea v-model="form.special_requests" class="w-full rounded-lg border-gray-300" rows="3"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <select v-model="form.status" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500" required>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>

        <div class="flex justify-end gap-2 mt-8">
          <Link :href="route('reservations.index')" class="px-4 py-2 rounded bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200">Batal</Link>
          <button :disabled="loading" class="px-6 py-2 rounded bg-pink-600 text-white font-bold hover:bg-pink-700">
            <span v-if="loading"><i class="fa fa-spinner fa-spin"></i> Menyimpan...</span>
            <span v-else>Simpan</span>
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import VueTimepicker from 'vue3-timepicker';
import 'vue3-timepicker/dist/VueTimepicker.css';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  reservation: Object,
  outlets: Array,
  isEdit: Boolean
});

const loading = ref(false);

const form = ref({
  name: props.reservation?.name || '',
  phone: props.reservation?.phone || '',
  email: props.reservation?.email || '',
  outlet_id: props.reservation?.outlet_id || '',
  reservation_date: props.reservation?.reservation_date || '',
  reservation_time: props.reservation?.reservation_time || '',
  number_of_guests: props.reservation?.number_of_guests || 1,
  smoking_preference: props.reservation?.smoking_preference || '',
  special_requests: props.reservation?.special_requests || '',
  status: props.reservation?.status || 'pending',
});

function submit() {
  loading.value = true;
  if (props.isEdit) {
    router.put(route('reservations.update', props.reservation.id), form.value, {
      onSuccess: () => {
        loading.value = false;
        Swal.fire('Sukses', 'Reservasi berhasil diupdate!', 'success');
      },
      onError: () => {
        loading.value = false;
        Swal.fire('Gagal', 'Terjadi kesalahan saat menyimpan reservasi.', 'error');
      }
    });
  } else {
    router.post(route('reservations.store'), form.value, {
      onSuccess: () => {
        loading.value = false;
        Swal.fire('Sukses', 'Reservasi berhasil disimpan!', 'success');
      },
      onError: () => {
        loading.value = false;
        Swal.fire('Gagal', 'Terjadi kesalahan saat menyimpan reservasi.', 'error');
      }
    });
  }
}
</script> 