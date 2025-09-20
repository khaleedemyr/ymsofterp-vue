<script setup>
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  item: Object,
  type: String, // 'departemen' | 'area'
  departemens: Array,
});

const emit = defineEmits(['close', 'success']);

const form = ref({
  nama_departemen: '',
  kode_departemen: '',
  nama_area: '',
  departemen_id: '',
  deskripsi: '',
  status: 'A',
});

const errors = ref({});
const loading = ref(false);
const nextAreaCode = ref('');
const loadingCode = ref(false);

const isEdit = computed(() => props.mode === 'edit');
const title = computed(() => {
  if (props.type === 'departemen') {
    return isEdit.value ? 'Edit Departemen' : 'Tambah Departemen Baru';
  } else {
    return isEdit.value ? 'Edit Area' : 'Tambah Area Baru';
  }
});

// Reset form when modal opens/closes or item changes
watch([() => props.show, () => props.item, () => props.type], () => {
  if (props.show) {
    resetForm();
    if (isEdit.value && props.item) {
      if (props.type === 'departemen') {
        form.value = {
          nama_departemen: props.item.nama_departemen || '',
          kode_departemen: props.item.kode_departemen || '',
          deskripsi: props.item.deskripsi || '',
          status: props.item.status || 'A',
        };
      } else {
        form.value = {
          nama_area: props.item.nama_area || '',
          departemen_id: props.item.departemen_id || '',
          deskripsi: props.item.deskripsi || '',
          status: props.item.status || 'A',
        };
      }
    }
  }
});

// Watch departemen_id untuk fetch next area code
watch(() => form.value.departemen_id, () => {
  if (props.type === 'area' && !isEdit.value) {
    fetchNextAreaCode();
  }
});

function resetForm() {
  form.value = {
    nama_departemen: '',
    kode_departemen: '',
    nama_area: '',
    departemen_id: '',
    deskripsi: '',
    status: 'A',
  };
  errors.value = {};
}

function closeModal() {
  emit('close');
}

async function fetchNextAreaCode() {
  if (!form.value.departemen_id) {
    nextAreaCode.value = '';
    return;
  }

  loadingCode.value = true;
  try {
    const response = await axios.get('/master-report/next-area-code', {
      params: { departemen_id: form.value.departemen_id }
    });
    
    if (response.data.success) {
      nextAreaCode.value = response.data.next_code;
    }
  } catch (error) {
    console.error('Error fetching next area code:', error);
    nextAreaCode.value = '';
  } finally {
    loadingCode.value = false;
  }
}

async function submitForm() {
  loading.value = true;
  errors.value = {};

  try {
    const url = isEdit.value 
      ? `/master-report/${props.item.id}?type=${props.type}`
      : `/master-report?type=${props.type}`;
    
    const method = isEdit.value ? 'put' : 'post';
    
    const response = await axios[method](url, {
      ...form.value,
      type: props.type,
    });

    if (response.data.success) {
      emit('success', response.data.message);
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors;
    } else {
      Swal.fire('Error', error.response?.data?.message || 'Terjadi kesalahan', 'error');
    }
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
          <i :class="type === 'departemen' ? 'fa-solid fa-building text-blue-500' : 'fa-solid fa-map-marker-alt text-green-500'"></i>
          {{ title }}
        </h2>
        <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition">
          <i class="fa-solid fa-times text-xl"></i>
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="submitForm" class="p-6 space-y-4">
        <!-- Departemen Fields -->
        <template v-if="type === 'departemen'">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Departemen *</label>
            <input
              v-model="form.nama_departemen"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
              :class="{ 'border-red-500': errors.nama_departemen }"
              placeholder="Masukkan nama departemen"
            />
            <p v-if="errors.nama_departemen" class="text-red-500 text-xs mt-1">{{ errors.nama_departemen[0] }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Kode Departemen *</label>
            <input
              v-model="form.kode_departemen"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition font-mono"
              :class="{ 'border-red-500': errors.kode_departemen }"
              placeholder="Masukkan kode departemen"
            />
            <p v-if="errors.kode_departemen" class="text-red-500 text-xs mt-1">{{ errors.kode_departemen[0] }}</p>
          </div>
        </template>

        <!-- Area Fields -->
        <template v-else>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Area *</label>
            <input
              v-model="form.nama_area"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
              :class="{ 'border-red-500': errors.nama_area }"
              placeholder="Masukkan nama area"
            />
            <p v-if="errors.nama_area" class="text-red-500 text-xs mt-1">{{ errors.nama_area[0] }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Departemen *</label>
            <select
              v-model="form.departemen_id"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
              :class="{ 'border-red-500': errors.departemen_id }"
            >
              <option value="">Pilih Departemen</option>
              <option v-for="dept in departemens" :key="dept.id" :value="dept.id">
                {{ dept.nama_departemen }}
              </option>
            </select>
            <p v-if="errors.departemen_id" class="text-red-500 text-xs mt-1">{{ errors.departemen_id[0] }}</p>
          </div>

          <!-- Info Kode Area Auto Generate -->
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
            <div class="flex items-center gap-2">
              <i class="fa-solid fa-info-circle text-blue-500"></i>
              <span class="text-sm font-medium text-blue-700">Kode Area Otomatis</span>
            </div>
            <p class="text-xs text-blue-600 mt-1">
              Kode area akan dibuat otomatis dengan sequence berdasarkan data terakhir.
              <br>
              <span class="font-mono text-xs">Format: OPS + 3 digit nomor urut</span>
            </p>
            
            <!-- Preview Kode Area -->
            <div v-if="nextAreaCode" class="mt-2 p-2 bg-white border border-blue-300 rounded">
              <div class="flex items-center gap-2">
                <i class="fa-solid fa-eye text-blue-500"></i>
                <span class="text-xs font-medium text-blue-700">Preview Kode:</span>
                <span class="font-mono text-sm font-bold text-blue-800 bg-blue-100 px-2 py-1 rounded">
                  {{ nextAreaCode }}
                </span>
              </div>
            </div>
            
            <!-- Loading State -->
            <div v-else-if="loadingCode" class="mt-2 p-2 bg-white border border-blue-300 rounded">
              <div class="flex items-center gap-2">
                <div class="animate-spin rounded-full h-3 w-3 border-2 border-blue-500 border-t-transparent"></div>
                <span class="text-xs text-blue-600">Mengambil kode berikutnya...</span>
              </div>
            </div>
          </div>
        </template>

        <!-- Deskripsi -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
          <textarea
            v-model="form.deskripsi"
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition resize-none"
            :class="{ 'border-red-500': errors.deskripsi }"
            placeholder="Masukkan deskripsi (opsional)"
          ></textarea>
          <p v-if="errors.deskripsi" class="text-red-500 text-xs mt-1">{{ errors.deskripsi[0] }}</p>
        </div>

        <!-- Status -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
          <select
            v-model="form.status"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
            :class="{ 'border-red-500': errors.status }"
          >
            <option value="A">Aktif</option>
            <option value="N">Non-Aktif</option>
          </select>
          <p v-if="errors.status" class="text-red-500 text-xs mt-1">{{ errors.status[0] }}</p>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 pt-4">
          <button
            type="button"
            @click="closeModal"
            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium"
            :disabled="loading"
          >
            Batal
          </button>
          <button
            type="submit"
            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium flex items-center justify-center gap-2"
            :disabled="loading"
          >
            <div v-if="loading" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
            <i v-else :class="isEdit ? 'fa-solid fa-save' : 'fa-solid fa-plus'"></i>
            {{ loading ? 'Menyimpan...' : (isEdit ? 'Simpan' : 'Tambah') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
