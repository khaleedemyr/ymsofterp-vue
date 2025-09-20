<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  outlets: Array,
  departments: Array,
});

const form = ref({
  outlet_id: '',
  inspection_time: 'lunch',
  department_id: ''
});




const loading = ref(false);
const errors = ref({});


async function submitForm() {
  loading.value = true;
  errors.value = {};

  try {
    const response = await axios.post('/daily-report', form.value);
    
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      router.visit(`/daily-report/${response.data.report_id}/inspect`);
    }
  } catch (error) {
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors;
    }
    Swal.fire('Error', error.response?.data?.message || 'Gagal membuat daily report', 'error');
  } finally {
    loading.value = false;
  }
}

function goBack() {
  router.visit('/daily-report');
}




</script>

<template>
  <AppLayout title="Buat Daily Report">
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-4 mb-6">
        <button @click="goBack" class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition">
          <i class="fa-solid fa-arrow-left text-gray-600"></i>
        </button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-plus-circle text-blue-500"></i> Buat Daily Report Baru
        </h1>
      </div>

      <div class="bg-white rounded-2xl shadow-lg p-6">
        <form @submit.prevent="submitForm">
          <!-- Selection Form -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Outlet -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
              <select 
                v-model="form.outlet_id" 
                class="form-input rounded-xl" 
                required
              >
                <option value="">Pilih Outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
              <p v-if="errors.outlet_id" class="text-red-500 text-xs mt-1">{{ errors.outlet_id[0] }}</p>
            </div>

            <!-- Inspection Time -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Waktu Inspection</label>
              <div class="flex gap-4">
                <label class="flex items-center">
                  <input type="radio" v-model="form.inspection_time" value="lunch" class="mr-2">
                  <span>Lunch</span>
                </label>
                <label class="flex items-center">
                  <input type="radio" v-model="form.inspection_time" value="dinner" class="mr-2">
                  <span>Dinner</span>
                </label>
              </div>
              <p v-if="errors.inspection_time" class="text-red-500 text-xs mt-1">{{ errors.inspection_time[0] }}</p>
            </div>

            <!-- Department -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
              <select v-model="form.department_id" class="form-input rounded-xl" required>
                <option value="">Pilih Department</option>
                <option v-for="department in departments" :key="department.id" :value="department.id">
                  {{ department.nama_departemen }}
                </option>
              </select>
              <p v-if="errors.department_id" class="text-red-500 text-xs mt-1">{{ errors.department_id[0] }}</p>
            </div>
          </div>

          <!-- Info Message -->
          <div v-if="form.department_id" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center gap-2">
              <i class="fa-solid fa-info-circle text-blue-500"></i>
              <span class="text-sm font-medium text-blue-700">Semua Area Akan Di-inspect</span>
            </div>
            <p class="text-xs text-blue-600 mt-1">
              Semua area di departemen yang dipilih akan otomatis dimasukkan ke dalam inspeksi.
            </p>
          </div>

          <!-- Action Buttons -->
          <div class="flex gap-4 mt-8 pt-6 border-t border-gray-200">
            <button type="button" @click="goBack" class="px-6 py-2 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-50 transition">
              Batal
            </button>
            <button 
              type="submit" 
              :disabled="loading"
              class="px-6 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2"
            >
              <div v-if="loading" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
              <i v-else class="fa-solid fa-play"></i>
              {{ loading ? 'Membuat...' : 'Mulai Inspection' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
