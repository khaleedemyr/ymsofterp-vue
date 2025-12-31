<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  member: Object,
});

function goBack() {
  router.visit('/members');
}

function edit() {
  router.visit(`/members/${props.member.id}/edit`);
}

function getStatusBadgeClass(status) {
  if (status === 'Diblokir') return 'bg-red-100 text-red-800';
  if (status === 'Aktif') return 'bg-green-100 text-green-800';
  return 'bg-gray-100 text-gray-800';
}


function getGenderBadgeClass(gender) {
  return gender === 'Laki-laki' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800';
}
</script>

<template>
  <AppLayout :title="`Detail Member - ${member.name}`">
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-user text-purple-500"></i> Detail Member
        </h1>
        <div class="flex gap-2">
          <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl hover:bg-gray-600 transition">
            <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
          </button>
          <button @click="edit" class="bg-purple-600 text-white px-4 py-2 rounded-xl hover:bg-purple-700 transition">
            <i class="fa-solid fa-edit mr-2"></i> Edit
          </button>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <!-- Header with Status -->
        <div class="bg-gradient-to-r from-purple-600 to-purple-800 text-white p-6">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-2xl font-bold">{{ member.nama_lengkap || member.name }}</h2>
              <p class="text-purple-200">{{ member.member_id || member.costumers_id }}</p>
            </div>
            <div class="text-right">
              <span :class="['px-3 py-1 rounded-full text-sm font-medium', member.is_active ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800']">
                {{ member.is_active ? 'Aktif' : 'Tidak Aktif' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="p-6">
          <!-- Basic Information -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div>
              <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-info-circle text-purple-500"></i>
                Informasi Dasar
              </h3>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-gray-600">ID Member:</span>
                  <span class="font-mono font-semibold">{{ member.member_id || member.costumers_id }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Nama Lengkap:</span>
                  <span class="font-semibold">{{ member.nama_lengkap || member.name }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Email:</span>
                  <span class="font-semibold">{{ member.email || '-' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Nomor Telepon:</span>
                  <span class="font-semibold">{{ member.mobile_phone || member.telepon || '-' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Tanggal Lahir:</span>
                  <span class="font-semibold">{{ member.tanggal_lahir ? new Date(member.tanggal_lahir).toLocaleDateString('id-ID') : '-' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Jenis Kelamin:</span>
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', getGenderBadgeClass(member.jenis_kelamin_text || (member.jenis_kelamin === 'L' ? 'Laki-laki' : member.jenis_kelamin === 'P' ? 'Perempuan' : '-'))]">
                    {{ member.jenis_kelamin_text || (member.jenis_kelamin === 'L' ? 'Laki-laki' : member.jenis_kelamin === 'P' ? 'Perempuan' : '-') }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Pekerjaan:</span>
                  <span class="font-semibold">{{ member.pekerjaan_name || member.occupation?.name || '-' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Saldo Point:</span>
                  <span class="font-semibold text-green-600">{{ member.just_points ? new Intl.NumberFormat('id-ID').format(member.just_points) : '0' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Tier:</span>
                  <span :class="[
                    'px-2 py-1 rounded-full text-xs font-medium uppercase',
                    member.member_level === 'platinum' 
                      ? 'bg-purple-100 text-purple-800 border border-purple-300'
                      : member.member_level === 'gold'
                      ? 'bg-yellow-100 text-yellow-800 border border-yellow-300'
                      : member.member_level === 'silver'
                      ? 'bg-gray-100 text-gray-800 border border-gray-300'
                      : 'bg-blue-100 text-blue-800 border border-blue-300'
                  ]">
                    {{ member.member_level || 'Silver' }}
                  </span>
                </div>
              </div>
            </div>

            <div>
              <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-shield-alt text-purple-500"></i>
                Status & Keanggotaan
              </h3>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-gray-600">Status Aktif:</span>
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', member.is_active ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800']">
                    {{ member.is_active ? 'Aktif' : 'Tidak Aktif' }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Total Spending:</span>
                  <span class="font-semibold text-blue-600">{{ member.total_spending ? 'Rp ' + new Intl.NumberFormat('id-ID').format(member.total_spending) : 'Rp 0' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Point Remainder:</span>
                  <span class="font-semibold">{{ member.point_remainder ? new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(member.point_remainder) : '0.00' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Email Verified:</span>
                  <span class="font-semibold">{{ member.email_verified_at ? new Date(member.email_verified_at).toLocaleString('id-ID') : 'Belum diverifikasi' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Mobile Verified:</span>
                  <span class="font-semibold">{{ member.mobile_verified_at ? new Date(member.mobile_verified_at).toLocaleString('id-ID') : 'Belum diverifikasi' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Last Login:</span>
                  <span class="font-semibold">{{ member.last_login_at ? new Date(member.last_login_at).toLocaleString('id-ID') : '-' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Allow Notification:</span>
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', member.allow_notification ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">
                    {{ member.allow_notification ? 'Ya' : 'Tidak' }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Security Information -->
          <div class="border-t pt-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
              <i class="fa-solid fa-lock text-purple-500"></i>
              Informasi Keamanan
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-gray-600">Password:</span>
                  <span class="font-mono">{{ member.password ? '••••••••' : '-' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">PIN:</span>
                  <span class="font-mono">{{ member.pin ? '••••' : '-' }}</span>
                </div>
              </div>
            </div>
          </div>


          <!-- Timestamps -->
          <div class="border-t pt-6 mt-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
              <i class="fa-solid fa-clock text-purple-500"></i>
              Informasi Sistem
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-gray-600">Dibuat:</span>
                  <span class="font-semibold">{{ new Date(member.created_at).toLocaleString('id-ID') }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Terakhir Update:</span>
                  <span class="font-semibold">{{ new Date(member.updated_at).toLocaleString('id-ID') }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template> 