<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  user: Object,
});

function goBack() {
  router.visit('/users');
}
</script>

<template>
  <AppLayout :title="`Detail Karyawan - ${props.user.nama_lengkap}`">
    <div class="max-w-3xl mx-auto bg-white rounded-xl shadow-lg p-8 mt-8">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800 flex items-center gap-2">
          <i class="fa-solid fa-user text-blue-500"></i> Detail Karyawan
        </h1>
        <button @click="goBack" class="bg-blue-100 text-blue-700 px-4 py-2 rounded shadow hover:bg-blue-200 transition flex items-center gap-2">
          <i class="fa-solid fa-arrow-left"></i> Kembali
        </button>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <div class="mb-2"><span class="font-semibold">NIK:</span> {{ user.nik }}</div>
          <div class="mb-2"><span class="font-semibold">No KTP:</span> {{ user.no_ktp }}</div>
          <div class="mb-2"><span class="font-semibold">Nama:</span> {{ user.nama_lengkap }}</div>
          <div class="mb-2"><span class="font-semibold">Jabatan:</span> {{ user.nama_jabatan || '-' }}</div>
          <div class="mb-2"><span class="font-semibold">Outlet:</span> {{ user.nama_outlet || '-' }}</div>
          <div class="mb-2"><span class="font-semibold">Email:</span> {{ user.email }}</div>
          <div class="mb-2"><span class="font-semibold">No HP:</span> {{ user.no_hp }}</div>
          <div class="mb-2"><span class="font-semibold">Jenis Kelamin:</span> {{ user.jenis_kelamin }}</div>
          <div class="mb-2"><span class="font-semibold">Tempat, Tanggal Lahir:</span> {{ user.tempat_lahir }}, {{ user.tanggal_lahir }}</div>
          <div class="mb-2"><span class="font-semibold">Alamat:</span> {{ user.alamat }}</div>
          <div class="mb-2"><span class="font-semibold">Status Pernikahan:</span> {{ user.status_pernikahan }}</div>
          <div class="mb-2"><span class="font-semibold">Agama:</span> {{ user.agama }}</div>
        </div>
        <div>
          <div class="mb-2"><span class="font-semibold">Divisi:</span> {{ user.division_id || '-' }}</div>
          <div class="mb-2"><span class="font-semibold">Tanggal Masuk:</span> {{ user.tanggal_masuk }}</div>
          <div class="mb-2"><span class="font-semibold">No KK:</span> {{ user.nomor_kk }}</div>
          <div class="mb-2"><span class="font-semibold">Golongan Darah:</span> {{ user.golongan_darah }}</div>
          <div class="mb-2"><span class="font-semibold">NPWP:</span> {{ user.npwp_number }}</div>
          <div class="mb-2"><span class="font-semibold">BPJS Kesehatan:</span> {{ user.bpjs_health_number }}</div>
          <div class="mb-2"><span class="font-semibold">BPJS Ketenagakerjaan:</span> {{ user.bpjs_employment_number }}</div>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
        <div v-if="user.foto_ktp">
          <div class="font-semibold mb-2">Foto KTP</div>
          <img :src="`/storage/${user.foto_ktp}`" alt="Foto KTP" class="rounded shadow w-full object-contain max-h-48" />
        </div>
        <div v-if="user.foto_kk">
          <div class="font-semibold mb-2">Foto KK</div>
          <img :src="`/storage/${user.foto_kk}`" alt="Foto KK" class="rounded shadow w-full object-contain max-h-48" />
        </div>
        <div v-if="user.upload_latest_color_photo">
          <div class="font-semibold mb-2">Foto Terbaru</div>
          <img :src="`/storage/${user.upload_latest_color_photo}`" alt="Foto Terbaru" class="rounded shadow w-full object-contain max-h-48" />
        </div>
      </div>
    </div>
  </AppLayout>
</template> 