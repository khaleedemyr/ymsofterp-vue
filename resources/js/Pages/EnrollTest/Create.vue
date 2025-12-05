<template>
  <AppLayout title="Tambah Enrollment Test">
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Tambah Enrollment Test
        </h2>
        <button @click="goBack" 
                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <i class="fa-solid fa-arrow-left"></i>
          Kembali
        </button>
      </div>
    </template>

    <div class="max-w-4xl mx-auto">
      <form @submit.prevent="submitForm" class="space-y-6">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">Informasi Enrollment</h3>
            
            <!-- Master Soal -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Master Soal *
              </label>
              <select v-model="form.master_soal_id" 
                      :class="{ 'border-red-500': errors.master_soal_id }"
                      class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Pilih Master Soal</option>
                <option v-for="soal in masterSoals" :key="soal.id" :value="soal.id">
                  {{ soal.judul }} ({{ soal.pertanyaans?.length || 0 }} pertanyaan)
                </option>
              </select>
              <p v-if="errors.master_soal_id" class="mt-1 text-sm text-red-600">
                {{ errors.master_soal_id }}
              </p>
            </div>

            <!-- Users -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                User yang akan di-enroll *
              </label>
              <p class="text-sm text-gray-500 mb-3">
                Pilih satu atau lebih user untuk di-enroll ke soal yang dipilih. 
                Setiap user akan mendapat enrollment terpisah untuk soal yang sama.
              </p>
              
              <!-- Search Users -->
              <div class="mb-3">
                <input type="text" 
                       v-model="userSearch"
                       placeholder="Cari berdasarkan nama, email, jabatan, outlet, atau divisi..."
                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <div v-if="!userSearch" class="text-xs text-gray-400 mt-1">
                  Contoh: "Manager", "Jakarta", "IT", "admin@company.com"
                </div>
              </div>
              
              <!-- Select All/None -->
              <div class="flex gap-2 mb-3">
                <button type="button" @click="selectAllUsers" 
                        class="text-sm text-blue-600 hover:text-blue-800">
                  Pilih Semua
                </button>
                <button type="button" @click="deselectAllUsers" 
                        class="text-sm text-gray-600 hover:text-gray-800">
                  Batal Pilih Semua
                </button>
                <span class="text-sm text-gray-500">
                  ({{ user_ids.length }} user dipilih)
                </span>
                <span v-if="userSearch" class="text-sm text-blue-600">
                  - {{ filteredUsers.length }} hasil ditemukan
                </span>
              </div>
              
              <div class="border border-gray-300 rounded-md p-3 max-h-60 overflow-y-auto">
                <div v-for="user in filteredUsers" :key="user.id" class="flex items-start mb-3 p-2 border border-gray-200 rounded hover:bg-gray-50">
                  <input type="checkbox" 
                         :id="`user-${user.id}`"
                         :value="user.id"
                         v-model="user_ids"
                         class="mr-3 mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                  <label :for="`user-${user.id}`" class="text-sm text-gray-700 cursor-pointer flex-1">
                    <div class="font-medium text-gray-900" 
                         v-html="highlightText(user.nama_lengkap, userSearch)"></div>
                    <div class="text-xs text-gray-500 mb-1" 
                         v-html="highlightText(user.email, userSearch)"></div>
                    <div class="flex flex-wrap gap-2 text-xs">
                      <span v-if="user.jabatan?.nama_jabatan" 
                            class="inline-flex items-center px-2 py-1 rounded-full bg-blue-100 text-blue-800">
                        <i class="fa-solid fa-briefcase mr-1"></i>
                        <span v-html="highlightText(user.jabatan.nama_jabatan, userSearch)"></span>
                      </span>
                      <span v-if="user.outlet?.nama_outlet" 
                            class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-800">
                        <i class="fa-solid fa-store mr-1"></i>
                        <span v-html="highlightText(user.outlet.nama_outlet, userSearch)"></span>
                      </span>
                      <span v-if="user.divisi?.nama_divisi" 
                            class="inline-flex items-center px-2 py-1 rounded-full bg-purple-100 text-purple-800">
                        <i class="fa-solid fa-users mr-1"></i>
                        <span v-html="highlightText(user.divisi.nama_divisi, userSearch)"></span>
                      </span>
                    </div>
                  </label>
                </div>
                
                <div v-if="filteredUsers.length === 0" class="text-sm text-gray-500 text-center py-4">
                  <i class="fa-solid fa-search text-2xl mb-2 block"></i>
                  <div v-if="userSearch">
                    Tidak ada user yang cocok dengan "<strong>{{ userSearch }}</strong>"
                    <div class="text-xs mt-1">Coba cari berdasarkan nama, email, jabatan, outlet, atau divisi</div>
                  </div>
                  <div v-else>
                    Tidak ada user yang tersedia
                  </div>
                </div>
              </div>
              <p v-if="errors.user_ids" class="mt-1 text-sm text-red-600">
                {{ errors.user_ids }}
              </p>
              
              <!-- Selected Users Preview -->
              <div v-if="user_ids.length > 0" class="mt-3 p-3 bg-blue-50 rounded-md">
                <h4 class="text-sm font-medium text-blue-800 mb-2">
                  User yang dipilih ({{ user_ids.length }}):
                </h4>
                <div class="space-y-2">
                  <div v-for="userId in user_ids" :key="userId" 
                       class="flex items-center justify-between p-2 bg-white rounded-lg border border-blue-200">
                    <div class="flex-1">
                      <div class="font-medium text-gray-900">{{ getUserName(userId) }}</div>
                      <div class="text-xs text-gray-500">{{ getUserEmail(userId) }}</div>
                      <div class="flex flex-wrap gap-1 mt-1">
                        <span v-if="getUserJabatan(userId)" 
                              class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700">
                          <i class="fa-solid fa-briefcase mr-1"></i>
                          {{ getUserJabatan(userId) }}
                        </span>
                        <span v-if="getUserOutlet(userId)" 
                              class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">
                          <i class="fa-solid fa-store mr-1"></i>
                          {{ getUserOutlet(userId) }}
                        </span>
                        <span v-if="getUserDivisi(userId)" 
                              class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-700">
                          <i class="fa-solid fa-users mr-1"></i>
                          {{ getUserDivisi(userId) }}
                        </span>
                      </div>
                    </div>
                    <button type="button" @click="removeUser(userId)" 
                            class="ml-2 text-red-600 hover:text-red-800 p-1">
                      <i class="fa-solid fa-times"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>


            <!-- Max Attempts -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Maksimal Percobaan
              </label>
              <input type="number" 
                     v-model="form.max_attempts"
                     min="1" 
                     max="10"
                     class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
              <p class="mt-1 text-sm text-gray-500">
                Maksimal 10 percobaan.
              </p>
            </div>

            <!-- Outlet Selection -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Lokasi/Outlet
              </label>
              <select v-model="form.outlet_id" 
                      class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Pilih Outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
              <p class="mt-1 text-sm text-gray-500">
                Pilih outlet untuk enrollment test.
              </p>
            </div>

            <!-- Expired At -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Tanggal Kedaluwarsa
              </label>
              <input type="date" 
                     v-model="form.expired_at"
                     class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
              <p class="mt-1 text-sm text-gray-500">
                Kosongkan untuk tidak ada batas waktu.
              </p>
            </div>

            <!-- Notes -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Catatan
              </label>
              <textarea v-model="form.notes"
                       rows="3"
                       placeholder="Catatan tambahan untuk enrollment..."
                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-4">
          <button type="button" @click="goBack"
                  class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg">
            Batal
          </button>
          <button type="submit" 
                  :disabled="loading"
                  class="bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white px-6 py-2 rounded-lg flex items-center gap-2">
            <i v-if="loading" class="fa-solid fa-spinner fa-spin"></i>
            <i v-else class="fa-solid fa-save"></i>
            {{ loading ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  masterSoals: Array,
  users: Array,
  outlets: Array
});

// Reactive data
const loading = ref(false);
const errors = ref({});
const userSearch = ref('');

const user_ids = ref([]);

const form = reactive({
  master_soal_id: '',
  user_ids: user_ids,
  outlet_id: '',
  max_attempts: 1,
  expired_at: '',
  notes: ''
});

// Computed
const filteredUsers = computed(() => {
  if (!userSearch.value) {
    return props.users;
  }
  
  const searchTerm = userSearch.value.toLowerCase();
  
  return props.users.filter(user => {
    // Search in nama lengkap
    if (user.nama_lengkap?.toLowerCase().includes(searchTerm)) return true;
    
    // Search in email
    if (user.email?.toLowerCase().includes(searchTerm)) return true;
    
    // Search in jabatan
    if (user.jabatan?.nama_jabatan?.toLowerCase().includes(searchTerm)) return true;
    
    // Search in outlet
    if (user.outlet?.nama_outlet?.toLowerCase().includes(searchTerm)) return true;
    
    // Search in divisi
    if (user.divisi?.nama_divisi?.toLowerCase().includes(searchTerm)) return true;
    
    return false;
  });
});

// Methods
function submitForm() {
  loading.value = true;
  errors.value = {};

  // Validation
  if (!form.master_soal_id) {
    errors.value.master_soal_id = 'Master Soal harus dipilih';
    loading.value = false;
    return;
  }

  if (user_ids.value.length === 0) {
    errors.value.user_ids = 'Minimal satu user harus dipilih';
    loading.value = false;
    return;
  }

  // Prepare data
  const data = {
    master_soal_id: form.master_soal_id,
    user_ids: user_ids.value,
    outlet_id: form.outlet_id,
    max_attempts: form.max_attempts,
    expired_at: form.expired_at || null,
    notes: form.notes || null
  };

  router.post(route('enroll-test.store'), data, {
    onStart: () => {
      loading.value = true;
    },
    onFinish: () => {
      loading.value = false;
    },
    onError: (error) => {
      errors.value = error;
    }
  });
}

function goBack() {
  router.visit(route('enroll-test.index'));
}

function selectAllUsers() {
  user_ids.value = filteredUsers.value.map(user => user.id);
}

function deselectAllUsers() {
  user_ids.value = [];
}

function getUserName(userId) {
  const user = props.users.find(u => u.id === userId);
  return user ? user.nama_lengkap : 'Unknown User';
}

function getUserEmail(userId) {
  const user = props.users.find(u => u.id === userId);
  return user ? user.email : '';
}

function getUserJabatan(userId) {
  const user = props.users.find(u => u.id === userId);
  return user?.jabatan?.nama_jabatan || '';
}

function getUserOutlet(userId) {
  const user = props.users.find(u => u.id === userId);
  return user?.outlet?.nama_outlet || '';
}

function getUserDivisi(userId) {
  const user = props.users.find(u => u.id === userId);
  return user?.divisi?.nama_divisi || '';
}

function removeUser(userId) {
  const index = user_ids.value.indexOf(userId);
  if (index > -1) {
    user_ids.value.splice(index, 1);
  }
}

function highlightText(text, searchTerm) {
  if (!searchTerm || !text) return text;
  
  const regex = new RegExp(`(${searchTerm})`, 'gi');
  return text.replace(regex, '<mark class="bg-yellow-200 px-1 rounded">$1</mark>');
}
</script>
