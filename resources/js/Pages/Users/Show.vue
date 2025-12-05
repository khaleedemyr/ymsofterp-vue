<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  user: Object,
});

function goBack() {
  router.visit('/users');
}

function editUser() {
  router.visit(`/users/${props.user.id}/edit`);
}

// Helper functions for formatting
function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  });
}

function formatGender(gender) {
  if (gender === 'L') return 'Laki-laki';
  if (gender === 'P') return 'Perempuan';
  return gender || '-';
}

function formatStatus(status) {
  if (status === 'A') return 'Aktif';
  if (status === 'N') return 'Tidak Aktif';
  return status || '-';
}

function formatMaritalStatus(status) {
  const statusMap = {
    'L': 'Lajang',
    'M': 'Menikah',
    'D': 'Cerai',
    'W': 'Duda/Janda'
  };
  return statusMap[status] || status || '-';
}

function formatEducation(education) {
  const educationMap = {
    'SD': 'Sekolah Dasar',
    'SMP': 'SMP/Sederajat',
    'SMA': 'SMA/SMK/Sederajat',
    'D3': 'Diploma 3',
    'D4': 'Diploma 4',
    'S1': 'Sarjana (S1)',
    'S2': 'Magister (S2)',
    'S3': 'Doktor (S3)'
  };
  return educationMap[education] || education || '-';
}

function formatReligion(religion) {
  const religionMap = {
    'ISLAM': 'Islam',
    'KRISTEN': 'Kristen',
    'KATOLIK': 'Katolik',
    'HINDU': 'Hindu',
    'BUDDHA': 'Buddha',
    'KONGHUCU': 'Konghucu'
  };
  return religionMap[religion] || religion || '-';
}

function getInitials(name) {
  if (!name) return '';
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0,2);
}
</script>

<template>
  <AppLayout :title="`Detail Karyawan - ${props.user.nama_lengkap}`">
    <div class="max-w-6xl mx-auto bg-white rounded-xl shadow-lg overflow-hidden mt-8">
      <!-- Header -->
      <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-6">
        <div class="flex justify-between items-center">
          <div class="flex items-center gap-4">
            <!-- Avatar -->
            <div v-if="props.user.avatar" class="w-20 h-20 rounded-full overflow-hidden border-4 border-white/30 shadow-lg">
              <img :src="`/storage/${props.user.avatar}`" alt="Avatar" class="w-full h-full object-cover" />
            </div>
            <div v-else class="w-20 h-20 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white text-2xl font-bold border-4 border-white/30 shadow-lg">
              {{ getInitials(props.user.nama_lengkap) }}
            </div>
            <div>
              <h1 class="text-2xl font-bold">{{ props.user.nama_lengkap }}</h1>
              <p class="text-blue-100">{{ props.user.jabatan?.nama_jabatan || 'Jabatan tidak tersedia' }}</p>
              <p class="text-blue-100 text-sm">{{ props.user.outlet?.nama_outlet || 'Outlet tidak tersedia' }} • {{ props.user.divisi?.nama_divisi || 'Divisi tidak tersedia' }}</p>
            </div>
          </div>
          <div class="flex gap-3">
            <button @click="editUser" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition flex items-center gap-2">
              <i class="fa-solid fa-edit"></i> Edit
            </button>
            <button @click="goBack" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition flex items-center gap-2">
              <i class="fa-solid fa-arrow-left"></i> Kembali
            </button>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6">
        <!-- Basic Information -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Left Column -->
          <div class="space-y-6">
            <!-- Personal Information -->
            <div class="bg-gray-50 rounded-lg p-6">
              <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-user text-blue-600"></i>
                Informasi Pribadi
              </h2>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="text-sm font-medium text-gray-600">NIK</label>
                  <p class="text-gray-900 font-semibold">{{ props.user.nik || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">No KTP</label>
                  <p class="text-gray-900">{{ props.user.no_ktp || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Nama Lengkap</label>
                  <p class="text-gray-900 font-semibold">{{ props.user.nama_lengkap }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Nama Panggilan</label>
                  <p class="text-gray-900">{{ props.user.nama_panggilan || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Jenis Kelamin</label>
                  <p class="text-gray-900">{{ formatGender(props.user.jenis_kelamin) }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Tempat Lahir</label>
                  <p class="text-gray-900">{{ props.user.tempat_lahir || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Tanggal Lahir</label>
                  <p class="text-gray-900">{{ formatDate(props.user.tanggal_lahir) }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Suku</label>
                  <p class="text-gray-900">{{ props.user.suku || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Agama</label>
                  <p class="text-gray-900">{{ formatReligion(props.user.agama) }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Status Pernikahan</label>
                  <p class="text-gray-900">{{ formatMaritalStatus(props.user.status_pernikahan) }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Golongan Darah</label>
                  <p class="text-gray-900">{{ props.user.golongan_darah || '-' }}</p>
                </div>
              </div>
            </div>

            <!-- Contact Information -->
            <div class="bg-gray-50 rounded-lg p-6">
              <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-address-book text-blue-600"></i>
                Informasi Kontak
              </h2>
              <div class="space-y-4">
                <div>
                  <label class="text-sm font-medium text-gray-600">Email</label>
                  <p class="text-gray-900">{{ props.user.email || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">No HP</label>
                  <p class="text-gray-900">{{ props.user.no_hp || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">IMEI</label>
                  <p class="text-gray-900">{{ props.user.imei || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Alamat</label>
                  <p class="text-gray-900">{{ props.user.alamat || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Alamat KTP</label>
                  <p class="text-gray-900">{{ props.user.alamat_ktp || '-' }}</p>
                </div>
              </div>
            </div>

            <!-- Emergency Contact -->
            <div class="bg-gray-50 rounded-lg p-6">
              <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-phone text-red-600"></i>
                Kontak Darurat
              </h2>
              <div class="space-y-4">
                <div>
                  <label class="text-sm font-medium text-gray-600">Nama Kontak Darurat</label>
                  <p class="text-gray-900">{{ props.user.nama_kontak_darurat || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">No HP Kontak Darurat</label>
                  <p class="text-gray-900">{{ props.user.no_hp_kontak_darurat || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Hubungan</label>
                  <p class="text-gray-900">{{ props.user.hubungan_kontak_darurat || '-' }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column -->
          <div class="space-y-6">
            <!-- Work Information -->
            <div class="bg-gray-50 rounded-lg p-6">
              <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-briefcase text-green-600"></i>
                Informasi Pekerjaan
              </h2>
              <div class="space-y-4">
                <div>
                  <label class="text-sm font-medium text-gray-600">Jabatan</label>
                  <p class="text-gray-900 font-semibold">{{ props.user.jabatan?.nama_jabatan || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Outlet</label>
                  <p class="text-gray-900">{{ props.user.outlet?.nama_outlet || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Divisi</label>
                  <p class="text-gray-900">{{ props.user.divisi?.nama_divisi || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Tanggal Masuk</label>
                  <p class="text-gray-900">{{ formatDate(props.user.tanggal_masuk) }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Status</label>
                  <span :class="props.user.status === 'A' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 py-1 rounded-full text-xs font-medium">
                    {{ formatStatus(props.user.status) }}
                  </span>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">PIN POS</label>
                  <p class="text-gray-900">{{ props.user.pin_pos || '-' }}</p>
                </div>
              </div>
            </div>

            <!-- Financial Information -->
            <div class="bg-gray-50 rounded-lg p-6">
              <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-credit-card text-purple-600"></i>
                Informasi Keuangan
              </h2>
              <div class="space-y-4">
                <div>
                  <label class="text-sm font-medium text-gray-600">Nama Rekening</label>
                  <p class="text-gray-900">{{ props.user.nama_rekening || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">No Rekening</label>
                  <p class="text-gray-900">{{ props.user.no_rekening || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">NPWP</label>
                  <p class="text-gray-900">{{ props.user.npwp_number || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">BPJS Kesehatan</label>
                  <p class="text-gray-900">{{ props.user.bpjs_health_number || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">BPJS Ketenagakerjaan</label>
                  <p class="text-gray-900">{{ props.user.bpjs_employment_number || '-' }}</p>
                </div>
              </div>
            </div>

            <!-- Education Information -->
            <div class="bg-gray-50 rounded-lg p-6">
              <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-graduation-cap text-indigo-600"></i>
                Informasi Pendidikan
              </h2>
              <div class="space-y-4">
                <div>
                  <label class="text-sm font-medium text-gray-600">Pendidikan Terakhir</label>
                  <p class="text-gray-900">{{ formatEducation(props.user.last_education) }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Nama Sekolah/Kampus</label>
                  <p class="text-gray-900">{{ props.user.name_school_college || '-' }}</p>
                </div>
                <div>
                  <label class="text-sm font-medium text-gray-600">Jurusan</label>
                  <p class="text-gray-900">{{ props.user.school_college_major || '-' }}</p>
                </div>
              </div>
            </div>

            <!-- Additional Information -->
            <div class="bg-gray-50 rounded-lg p-6">
              <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-info-circle text-orange-600"></i>
                Informasi Tambahan
              </h2>
              <div class="space-y-4">
                <div>
                  <label class="text-sm font-medium text-gray-600">No KK</label>
                  <p class="text-gray-900">{{ props.user.nomor_kk || '-' }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Photos Section -->
        <div class="mt-8">
          <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-images text-pink-600"></i>
            Dokumen & Foto
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div v-if="props.user.foto_ktp" class="bg-gray-50 rounded-lg p-4">
              <h3 class="font-semibold text-gray-800 mb-3">Foto KTP</h3>
              <img :src="`/storage/${props.user.foto_ktp}`" alt="Foto KTP" class="w-full h-48 object-cover rounded-lg shadow-md" />
            </div>
            <div v-else class="bg-gray-50 rounded-lg p-4">
              <h3 class="font-semibold text-gray-800 mb-3">Foto KTP</h3>
              <div class="w-full h-48 bg-gray-200 rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-image text-gray-400 text-3xl"></i>
              </div>
            </div>

            <div v-if="props.user.foto_kk" class="bg-gray-50 rounded-lg p-4">
              <h3 class="font-semibold text-gray-800 mb-3">Foto KK</h3>
              <img :src="`/storage/${props.user.foto_kk}`" alt="Foto KK" class="w-full h-48 object-cover rounded-lg shadow-md" />
            </div>
            <div v-else class="bg-gray-50 rounded-lg p-4">
              <h3 class="font-semibold text-gray-800 mb-3">Foto KK</h3>
              <div class="w-full h-48 bg-gray-200 rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-image text-gray-400 text-3xl"></i>
              </div>
            </div>

            <div v-if="props.user.upload_latest_color_photo" class="bg-gray-50 rounded-lg p-4">
              <h3 class="font-semibold text-gray-800 mb-3">Foto Terbaru</h3>
              <img :src="`/storage/${props.user.upload_latest_color_photo}`" alt="Foto Terbaru" class="w-full h-48 object-cover rounded-lg shadow-md" />
            </div>
            <div v-else class="bg-gray-50 rounded-lg p-4">
              <h3 class="font-semibold text-gray-800 mb-3">Foto Terbaru</h3>
              <div class="w-full h-48 bg-gray-200 rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-image text-gray-400 text-3xl"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template> 