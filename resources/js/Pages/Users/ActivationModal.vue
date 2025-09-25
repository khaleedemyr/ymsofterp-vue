<template>
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b">
        <h3 class="text-lg font-semibold text-gray-900">
          {{ user?.status === 'B' ? 'Aktivasi Karyawan Baru' : 'Lengkapi Data Karyawan' }}
        </h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
          <i class="fa-solid fa-times text-xl"></i>
        </button>
      </div>

      <!-- Content -->
      <div class="p-6">
        <div class="mb-4">
          <p class="text-sm text-gray-600 mb-2">
            {{ user?.status === 'B' ? 'Karyawan baru yang akan diaktifkan:' : 'Karyawan yang perlu melengkapi data:' }}
          </p>
          <div class="bg-gray-50 p-3 rounded-lg">
            <p class="font-semibold">{{ user?.nama_lengkap }}</p>
            <p class="text-sm text-gray-600">NIK: {{ user?.nik }}</p>
            <p class="text-sm text-gray-600">Status: {{ user?.status === 'B' ? 'Baru' : user?.status === 'A' ? 'Aktif' : 'Non-Aktif' }}</p>
          </div>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <!-- Jabatan -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Jabatan <span class="text-red-500">*</span>
            </label>
            <Multiselect
              v-model="form.jabatan_id"
              :options="jabatanOptions"
              :searchable="true"
              :clear-on-select="false"
              :close-on-select="true"
              :show-labels="false"
              track-by="id"
              label="name"
              placeholder="Pilih jabatan..."
              :class="{ 'border-red-500': errors.jabatan_id }"
              class="w-full"
            />
            <p v-if="errors.jabatan_id" class="text-red-500 text-sm mt-1">{{ errors.jabatan_id }}</p>
          </div>

          <!-- Divisi -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Divisi <span class="text-red-500">*</span>
            </label>
            <Multiselect
              v-model="form.division_id"
              :options="divisionOptions"
              :searchable="true"
              :clear-on-select="false"
              :close-on-select="true"
              :show-labels="false"
              track-by="id"
              label="name"
              placeholder="Pilih divisi..."
              :class="{ 'border-red-500': errors.division_id }"
              class="w-full"
            />
            <p v-if="errors.division_id" class="text-red-500 text-sm mt-1">{{ errors.division_id }}</p>
          </div>

          <!-- Outlet -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Outlet <span class="text-red-500">*</span>
            </label>
            <Multiselect
              v-model="form.outlet_id"
              :options="outletOptions"
              :searchable="true"
              :clear-on-select="false"
              :close-on-select="true"
              :show-labels="false"
              track-by="id"
              label="name"
              placeholder="Pilih outlet..."
              :class="{ 'border-red-500': errors.outlet_id }"
              class="w-full"
            />
            <p v-if="errors.outlet_id" class="text-red-500 text-sm mt-1">{{ errors.outlet_id }}</p>
          </div>

          <!-- Join Date -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Tanggal Bergabung <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.tanggal_masuk"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-500': errors.tanggal_masuk }"
              :max="new Date().toISOString().split('T')[0]"
            />
            <p v-if="errors.tanggal_masuk" class="text-red-500 text-sm mt-1">{{ errors.tanggal_masuk }}</p>
            <p class="text-xs text-gray-500 mt-1">Tanggal karyawan mulai bekerja di perusahaan</p>
          </div>

          <!-- Loading State -->
          <div v-if="loading" class="flex items-center justify-center py-4">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <span class="ml-2 text-gray-600">Memproses aktivasi...</span>
          </div>

          <!-- Error Message -->
          <div v-if="errorMessage" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
            {{ errorMessage }}
          </div>
        </form>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-end gap-3 p-6 border-t">
        <button
          @click="$emit('close')"
          type="button"
          class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition"
          :disabled="loading"
        >
          Batal
        </button>
        <button
          @click="handleSubmit"
          type="submit"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
          :disabled="loading || !isFormValid"
        >
          <span v-if="loading">Memproses...</span>
          <span v-else>{{ user?.status === 'B' ? 'Aktifkan Karyawan' : 'Simpan Data' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import axios from 'axios';

const props = defineProps({
  show: Boolean,
  user: Object,
  jabatans: Array,
  divisions: Array,
  outlets: Array,
});

const emit = defineEmits(['close', 'success']);

// Form data
const form = ref({
  jabatan_id: null,
  division_id: null,
  outlet_id: null,
  tanggal_masuk: '',
});

// State
const loading = ref(false);
const errors = ref({});
const errorMessage = ref('');

// Convert props to multiselect format
const jabatanOptions = computed(() => 
  props.jabatans?.map(j => ({ id: j.id_jabatan, name: j.nama_jabatan })) || []
);

const divisionOptions = computed(() => 
  props.divisions?.map(d => ({ id: d.id, name: d.nama_divisi })) || []
);

const outletOptions = computed(() => 
  props.outlets?.map(o => ({ id: o.id_outlet, name: o.nama_outlet })) || []
);

// Form validation
const isFormValid = computed(() => {
  return form.value.jabatan_id && form.value.division_id && form.value.outlet_id && form.value.tanggal_masuk;
});

// Reset form when modal opens/closes
watch(() => props.show, (newVal) => {
  if (newVal) {
    resetForm();
  }
});

function resetForm() {
  form.value = {
    jabatan_id: null,
    division_id: null,
    outlet_id: null,
    tanggal_masuk: '',
  };
  errors.value = {};
  errorMessage.value = '';
}

async function handleSubmit() {
  if (!isFormValid.value) {
    errors.value = {
      jabatan_id: !form.value.jabatan_id ? 'Jabatan harus dipilih' : '',
      division_id: !form.value.division_id ? 'Divisi harus dipilih' : '',
      outlet_id: !form.value.outlet_id ? 'Outlet harus dipilih' : '',
      tanggal_masuk: !form.value.tanggal_masuk ? 'Tanggal bergabung harus diisi' : '',
    };
    return;
  }

  loading.value = true;
  errors.value = {};
  errorMessage.value = '';

  try {
    const response = await axios.post(`/users/${props.user.id}/activate`, {
      jabatan_id: form.value.jabatan_id.id,
      division_id: form.value.division_id.id,
      outlet_id: form.value.outlet_id.id,
      tanggal_masuk: form.value.tanggal_masuk,
    });

    if (response.data.success) {
      emit('success', response.data.message);
      emit('close');
    }
  } catch (error) {
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors;
    } else {
      errorMessage.value = error.response?.data?.message || 'Terjadi kesalahan saat mengaktifkan karyawan';
    }
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
/* Custom styles for multiselect */
:deep(.multiselect) {
  min-height: 38px;
}

:deep(.multiselect__tags) {
  min-height: 38px;
  padding: 8px 40px 0 8px;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
}

:deep(.multiselect__input) {
  margin-bottom: 8px;
}

:deep(.multiselect__single) {
  margin-bottom: 8px;
  padding-left: 0;
}

:deep(.multiselect__placeholder) {
  margin-bottom: 8px;
  padding-top: 0;
  padding-bottom: 0;
}

:deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-top: none;
  border-radius: 0 0 0.375rem 0.375rem;
}

:deep(.multiselect__option) {
  padding: 8px 12px;
}

:deep(.multiselect__option--highlight) {
  background: #3b82f6;
}

:deep(.multiselect__option--selected) {
  background: #1d4ed8;
}
</style>
