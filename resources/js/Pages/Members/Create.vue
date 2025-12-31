<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  occupations: {
    type: Array,
    default: () => []
  }
});

// Debug: Log occupations
console.log('Occupations received:', props.occupations);

// Generate ID Member otomatis
function generateMemberId() {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
  return `MB${year}${month}${day}${random}`;
}

// Set valid until 1 tahun dari hari ini
function getValidUntilDate() {
  const today = new Date();
  const oneYearLater = new Date(today.getFullYear() + 1, today.getMonth(), today.getDate());
  return oneYearLater.toISOString().split('T')[0];
}

// Set tanggal register hari ini
function getTodayDate() {
  return new Date().toISOString().split('T')[0];
}

const form = ref({
  member_id: generateMemberId(),
  nama_lengkap: '',
  email: '',
  mobile_phone: '',
  tanggal_lahir: '',
  jenis_kelamin: '',
  pekerjaan_id: '',
  password: '',
  pin: '',
});

const errors = ref({});
const isSubmitting = ref(false);

function submit() {
  isSubmitting.value = true;
  router.post('/members', form.value, {
    onSuccess: () => {
      // Success handled by Inertia
    },
    onError: (validationErrors) => {
      errors.value = validationErrors;
      isSubmitting.value = false;
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
}

function cancel() {
  router.visit('/members');
}
</script>

<template>
  <AppLayout title="Tambah Member Baru">
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-user-plus text-purple-500"></i> Tambah Member Baru
        </h1>
        <button @click="cancel" class="bg-gray-500 text-white px-4 py-2 rounded-xl hover:bg-gray-600 transition">
          <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
        </button>
      </div>

      <div class="bg-white rounded-2xl shadow-lg p-6">
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Basic Information -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                ID Member
              </label>
              <input
                v-model="form.member_id"
                type="text"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 bg-gray-50 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
                :class="{ 'border-red-500': errors.member_id }"
                placeholder="ID Member akan digenerate otomatis"
                readonly
              />
              <p v-if="errors.member_id" class="text-red-500 text-sm mt-1">{{ errors.member_id }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Nama Lengkap <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.nama_lengkap"
                type="text"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
                :class="{ 'border-red-500': errors.nama_lengkap }"
                placeholder="Masukkan nama lengkap"
              />
              <p v-if="errors.nama_lengkap" class="text-red-500 text-sm mt-1">{{ errors.nama_lengkap }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Email
              </label>
              <input
                v-model="form.email"
                type="email"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
                :class="{ 'border-red-500': errors.email }"
                placeholder="Masukkan email"
              />
              <p v-if="errors.email" class="text-red-500 text-sm mt-1">{{ errors.email }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Nomor Telepon
              </label>
              <input
                v-model="form.mobile_phone"
                type="text"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
                :class="{ 'border-red-500': errors.mobile_phone }"
                placeholder="Masukkan nomor telepon"
              />
              <p v-if="errors.mobile_phone" class="text-red-500 text-sm mt-1">{{ errors.mobile_phone }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Tanggal Lahir
              </label>
              <input
                v-model="form.tanggal_lahir"
                type="date"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Jenis Kelamin
              </label>
              <select
                v-model="form.jenis_kelamin"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
              >
                <option value="">Pilih Jenis Kelamin</option>
                <option value="L">Laki-laki</option>
                <option value="P">Perempuan</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Pekerjaan
              </label>
              <select
                v-model="form.pekerjaan_id"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
                :class="{ 'border-red-500': errors.pekerjaan_id }"
              >
                <option value="">Pilih Pekerjaan (opsional)</option>
                <option v-for="occupation in occupations" :key="occupation.id" :value="occupation.id">
                  {{ occupation.name }}
                </option>
              </select>
              <p v-if="errors.pekerjaan_id" class="text-red-500 text-sm mt-1">{{ errors.pekerjaan_id }}</p>
            </div>
          </div>

          <!-- Security Information -->
          <div class="border-t pt-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Informasi Keamanan</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Password
                </label>
                <input
                  v-model="form.password"
                  type="password"
                  class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
                  placeholder="Masukkan password"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  PIN
                </label>
                <input
                  v-model="form.pin"
                  type="text"
                  maxlength="10"
                  class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400"
                  placeholder="Masukkan PIN"
                />
              </div>
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="flex justify-end gap-4 pt-6 border-t">
            <button
              type="button"
              @click="cancel"
              class="px-6 py-2 bg-gray-500 text-white rounded-xl hover:bg-gray-600 transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-6 py-2 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition disabled:opacity-50"
            >
              <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin mr-2"></i>
              {{ isSubmitting ? 'Menyimpan...' : 'Simpan Member' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template> 