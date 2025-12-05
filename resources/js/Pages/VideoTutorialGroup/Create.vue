<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const form = ref({
  name: '',
  description: '',
});

const errors = ref({});
const isSubmitting = ref(false);

async function submit() {
  // Clear previous errors
  errors.value = {};
  
  // Client-side validation
  if (!form.value.name.trim()) {
    errors.value.name = 'Nama group harus diisi';
    return;
  }
  
  // Show confirmation dialog
  const confirmed = await Swal.fire({
    title: 'Konfirmasi',
    text: 'Apakah Anda yakin ingin menyimpan group video tutorial ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#16a34a',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Simpan!',
    cancelButtonText: 'Batal',
    reverseButtons: true
  });
  
  if (!confirmed.isConfirmed) {
    return;
  }
  
  isSubmitting.value = true;
  
  try {
    await router.post('/video-tutorial-groups', form.value, {
      onSuccess: (page) => {
        isSubmitting.value = false;
        
        // Show success message
        Swal.fire({
          title: 'Berhasil!',
          text: 'Group video tutorial berhasil ditambahkan',
          icon: 'success',
          confirmButtonColor: '#16a34a',
          timer: 2000,
          showConfirmButton: false
        });
      },
      onError: (err) => {
        isSubmitting.value = false;
        errors.value = err;
        
        // Show error message
        Swal.fire({
          title: 'Error!',
          text: 'Terjadi kesalahan saat menyimpan data',
          icon: 'error',
          confirmButtonColor: '#dc2626'
        });
        
        console.error('Form submission error:', err);
      },
      onFinish: () => {
        isSubmitting.value = false;
      }
    });
  } catch (error) {
    isSubmitting.value = false;
    console.error('Unexpected error:', error);
    
    Swal.fire({
      title: 'Error!',
      text: 'Terjadi kesalahan yang tidak terduga',
      icon: 'error',
      confirmButtonColor: '#dc2626'
    });
  }
}

function cancel() {
  Swal.fire({
    title: 'Konfirmasi',
    text: 'Apakah Anda yakin ingin keluar? Data yang belum disimpan akan hilang.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Keluar',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.visit('/video-tutorial-groups');
    }
  });
}
</script>

<template>
  <AppLayout title="Tambah Group Video Tutorial">
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-folder-plus text-green-500"></i> Tambah Group Video Tutorial
        </h1>
        <button @click="cancel" class="bg-gray-500 text-white px-4 py-2 rounded-xl hover:bg-gray-600 transition">
          <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
        </button>
      </div>
      
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Nama Group <span class="text-red-500">*</span>
            </label>
            <input 
              v-model="form.name" 
              type="text" 
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-400 focus:border-green-400 transition"
              :class="{ 'border-red-500': errors.name }"
              placeholder="Masukkan nama group video tutorial"
              :disabled="isSubmitting"
            />
            <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
          </div>
          
          <!-- Description -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Deskripsi
            </label>
            <textarea 
              v-model="form.description" 
              rows="4"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-400 focus:border-green-400 transition"
              :class="{ 'border-red-500': errors.description }"
              placeholder="Masukkan deskripsi group video tutorial (opsional)"
              :disabled="isSubmitting"
            ></textarea>
            <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description }}</p>
          </div>
          
          <!-- Debug Info (temporary) -->
          <div v-if="Object.keys(errors).length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4">
            <h3 class="text-sm font-medium text-red-800 mb-2">Debug Info:</h3>
            <pre class="text-xs text-red-600">{{ JSON.stringify(errors, null, 2) }}</pre>
          </div>
          
          <!-- Submit Button -->
          <div class="flex justify-end gap-4 pt-6">
            <button 
              type="button" 
              @click="cancel"
              :disabled="isSubmitting"
              class="px-6 py-2 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Batal
            </button>
            <button 
              type="submit" 
              :disabled="isSubmitting || !form.name.trim()"
              class="px-6 py-2 bg-green-600 text-white rounded-xl hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin"></i>
              <i v-else class="fa-solid fa-save"></i>
              {{ isSubmitting ? 'Menyimpan...' : 'Simpan Group' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template> 