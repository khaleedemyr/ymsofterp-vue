<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <div>
          <Link
            href="/manual-point"
            class="text-blue-600 hover:text-blue-800 mb-2 inline-flex items-center gap-2"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar
          </Link>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-coins"></i> Inject Point Manual
          </h1>
        </div>
      </div>

      <!-- Info Alert -->
      <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded">
        <div class="flex">
          <div class="flex-shrink-0">
            <i class="fa-solid fa-info-circle text-blue-500"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm text-blue-700">
              Fitur ini digunakan untuk inject point manual ke member jika ada kegagalan dari POS. 
              Point yang di-inject akan memiliki expiry 1 tahun dari tanggal transaksi (atau sesuai yang diisi).
            </p>
          </div>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="submitForm" class="bg-white rounded-xl shadow-lg p-6 space-y-6">
        <!-- Member Selection -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Pilih Member <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <input
              type="text"
              v-model="memberSearch"
              @input="searchMembers"
              placeholder="Cari member (ID, Nama, Email, atau No. HP) - minimal 2 karakter"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
            <div v-if="searchingMembers" class="absolute right-3 top-3">
              <i class="fa fa-spinner fa-spin text-gray-400"></i>
            </div>
            <div v-if="memberOptions.length > 0 && memberSearch && memberSearch.length >= 2" 
              class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
              <div
                v-for="member in memberOptions"
                :key="member.id"
                @click="selectMember(member)"
                class="px-4 py-3 hover:bg-blue-50 cursor-pointer border-b last:border-b-0"
              >
                <div class="flex items-center justify-between">
                  <div class="flex-1">
                    <div class="font-semibold text-gray-900">{{ member.nama_lengkap }}</div>
                    <div class="text-sm text-gray-600">
                      {{ member.member_id }} | {{ member.email }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                      Level: {{ member.member_level || 'Silver' }}
                    </div>
                  </div>
                  <div class="text-sm text-blue-600 font-semibold ml-4">
                    {{ formatNumber(member.just_points || 0) }} points
                  </div>
                </div>
              </div>
            </div>
            <div v-if="memberSearch && memberSearch.length >= 2 && memberOptions.length === 0 && !searchingMembers" 
              class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg p-4 text-center text-gray-500">
              Tidak ada member yang ditemukan
            </div>
          </div>
          <!-- Selected Member Display -->
          <div v-if="selectedMember" class="mt-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex items-center justify-between">
              <div>
                <div class="font-semibold text-gray-900">{{ selectedMember.nama_lengkap }}</div>
                <div class="text-sm text-gray-600">{{ selectedMember.member_id }} | {{ selectedMember.email }}</div>
              </div>
              <button
                type="button"
                @click="clearMember"
                class="text-red-600 hover:text-red-800"
              >
                <i class="fa-solid fa-times"></i>
              </button>
            </div>
          </div>
          <div v-if="errors.member_id" class="mt-1 text-sm text-red-600">
            {{ errors.member_id }}
          </div>
        </div>

        <!-- Point Amount -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Jumlah Point <span class="text-red-500">*</span>
          </label>
          <input
            type="number"
            v-model.number="form.point_amount"
            required
            min="1"
            max="100000"
            placeholder="Masukkan jumlah point"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <div class="text-xs text-gray-500 mt-1">Min: 1, Max: 100,000 points</div>
          <div v-if="errors.point_amount" class="mt-1 text-sm text-red-600">
            {{ errors.point_amount }}
          </div>
        </div>

        <!-- Transaction Date -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Tanggal Transaksi <span class="text-red-500">*</span>
          </label>
          <input
            type="date"
            v-model="form.transaction_date"
            required
            :max="today"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <div v-if="errors.transaction_date" class="mt-1 text-sm text-red-600">
            {{ errors.transaction_date }}
          </div>
        </div>

        <!-- Reference ID -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Reference ID (Opsional)
          </label>
          <input
            type="text"
            v-model="form.reference_id"
            maxlength="255"
            placeholder="Contoh: ORDER-12345 atau kosongkan untuk auto-generate"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <div class="text-xs text-gray-500 mt-1">
            Jika kosong, akan auto-generate: MANUAL-YYYYMMDDHHMMSS-MEMBERID
          </div>
        </div>

        <!-- Expiry Date -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Tanggal Expiry (Opsional)
          </label>
          <input
            type="date"
            v-model="form.expires_at"
            :min="form.transaction_date || today"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <div class="text-xs text-gray-500 mt-1">
            Jika kosong, akan otomatis 1 tahun dari tanggal transaksi
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Keterangan <span class="text-red-500">*</span>
          </label>
          <textarea
            v-model="form.description"
            required
            maxlength="500"
            rows="4"
            placeholder="Contoh: Kompensasi point untuk order yang gagal di POS, Order ID: ORD-12345"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          ></textarea>
          <div class="text-xs text-gray-500 mt-1">
            {{ form.description.length }}/500 karakter
          </div>
          <div v-if="errors.description" class="mt-1 text-sm text-red-600">
            {{ errors.description }}
          </div>
        </div>

        <!-- Preview -->
        <div v-if="selectedMember" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <h3 class="text-sm font-semibold text-gray-700 mb-2">Preview:</h3>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Member:</span>
              <span class="font-semibold">{{ selectedMember.nama_lengkap }} ({{ selectedMember.member_id }})</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Point Saat Ini:</span>
              <span class="font-semibold">{{ selectedMember.just_points || 0 }} points</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Point yang akan di-inject:</span>
              <span class="font-semibold text-green-600">+{{ form.point_amount || 0 }} points</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Point Setelah Inject:</span>
              <span class="font-semibold text-blue-600">
                {{ (selectedMember.just_points || 0) + (form.point_amount || 0) }} points
              </span>
            </div>
          </div>
        </div>

        <!-- Error Message -->
        <div v-if="errors.error" class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
          <div class="flex">
            <div class="flex-shrink-0">
              <i class="fa-solid fa-exclamation-circle text-red-500"></i>
            </div>
            <div class="ml-3">
              <p class="text-sm text-red-700">{{ errors.error }}</p>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="flex gap-4">
          <button
            type="submit"
            :disabled="loading || !canSubmit"
            class="flex-1 px-6 py-3 bg-gradient-to-r from-green-500 to-green-700 text-white rounded-lg font-semibold hover:from-green-600 hover:to-green-800 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            <i v-if="loading" class="fa fa-spinner fa-spin"></i>
            <i v-else class="fa-solid fa-coins"></i>
            {{ loading ? 'Memproses...' : 'Inject Point' }}
          </button>
          <Link
            href="/manual-point"
            class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition"
          >
            Batal
          </Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  errors: Object,
});

const form = useForm({
  member_id: null,
  point_amount: null,
  transaction_date: new Date().toISOString().split('T')[0],
  reference_id: '',
  expires_at: '',
  description: '',
});

const memberOptions = ref([]);
const selectedMember = ref(null);
const memberSearch = ref('');
const searchingMembers = ref(false);
const loading = ref(false);
const today = new Date().toISOString().split('T')[0];

let searchTimeout = null;

const searchMembers = async () => {
  const search = memberSearch.value;
  
  if (!search || search.length < 2) {
    memberOptions.value = [];
    return;
  }

  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }

  searchingMembers.value = true;
  
  searchTimeout = setTimeout(async () => {
    try {
      const response = await axios.get('/manual-point/search-members', {
        params: { search: search }
      });
      memberOptions.value = response.data || [];
    } catch (error) {
      console.error('Error searching members:', error);
      memberOptions.value = [];
    } finally {
      searchingMembers.value = false;
    }
  }, 300);
};

// Handle member selection
const selectMember = (member) => {
  form.member_id = member.id;
  selectedMember.value = member;
  memberSearch.value = '';
  memberOptions.value = [];
};

// Clear member selection
const clearMember = () => {
  form.member_id = null;
  selectedMember.value = null;
  memberSearch.value = '';
  memberOptions.value = [];
};

const formatNumber = (num) => {
  if (!num) return '0';
  return new Intl.NumberFormat('id-ID').format(num);
};

const canSubmit = computed(() => {
  return form.member_id && 
         form.point_amount && 
         form.point_amount > 0 && 
         form.transaction_date && 
         form.description.trim().length > 0;
});

const submitForm = () => {
  if (!canSubmit.value) return;
  
  loading.value = true;
  
  form.post('/manual-point', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      form.transaction_date = new Date().toISOString().split('T')[0];
      selectedMember.value = null;
      memberOptions.value = [];
    },
    onFinish: () => {
      loading.value = false;
    },
  });
};

onMounted(() => {
  // Set default transaction date to today
  if (!form.transaction_date) {
    form.transaction_date = today;
  }
});
</script>


