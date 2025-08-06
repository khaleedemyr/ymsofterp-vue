<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
});

const emit = defineEmits(['close']);

const form = useForm({
  file: null,
});

const isUploading = ref(false);

function handleFileChange(event) {
  form.file = event.target.files[0];
}

function downloadTemplate() {
  window.location.href = route('roulette.download-template');
}

async function submit() {
  if (!form.file) {
    Swal.fire('Error', 'Pilih file Excel terlebih dahulu!', 'error');
    return;
  }

  isUploading.value = true;
  
  try {
    await form.post(route('roulette.import'), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'File berhasil diimport!', 'success');
        closeModal();
      },
      onError: (errors) => {
        Swal.fire('Gagal', 'Gagal import file!', 'error');
        console.error('Import error:', errors);
      },
    });
  } catch (e) {
    Swal.fire('Gagal', 'Gagal import file!', 'error');
    console.error('Import error:', e);
  } finally {
    isUploading.value = false;
  }
}

function closeModal() {
  form.reset();
  emit('close');
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Import Data Roulette</h3>
        <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="space-y-4">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h4 class="font-semibold text-blue-800 mb-2">Langkah Import:</h4>
          <ol class="text-sm text-blue-700 space-y-1">
            <li>1. Download template Excel di bawah ini</li>
            <li>2. Isi data sesuai format template</li>
            <li>3. Upload file Excel yang sudah diisi</li>
            <li>4. Klik Import untuk memproses data</li>
          </ol>
        </div>

        <div class="flex justify-center">
          <button 
            @click="downloadTemplate"
            class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition flex items-center gap-2"
          >
            <i class="fas fa-download"></i>
            Download Template Excel
          </button>
        </div>

        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
          <div class="mb-4">
            <i class="fas fa-file-excel text-4xl text-green-500"></i>
          </div>
          <div class="mb-4">
            <label for="file-upload" class="cursor-pointer">
              <span class="text-blue-500 hover:text-blue-600 font-medium">
                Pilih file Excel
              </span>
              <span class="text-gray-500"> atau drag & drop</span>
            </label>
            <input
              id="file-upload"
              type="file"
              @change="handleFileChange"
              accept=".xlsx,.xls"
              class="hidden"
            />
          </div>
          <p class="text-sm text-gray-500">
            Format: .xlsx, .xls (Maksimal 2MB)
          </p>
          <p v-if="form.file" class="text-sm text-green-600 mt-2">
            File dipilih: {{ form.file.name }}
          </p>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <h4 class="font-semibold text-yellow-800 mb-2">Catatan:</h4>
          <ul class="text-sm text-yellow-700 space-y-1">
            <li>• Nama adalah field wajib</li>
            <li>• Email harus valid (opsional)</li>
            <li>• No HP maksimal 15 digit (opsional)</li>
            <li>• Email tidak boleh duplikat</li>
          </ul>
        </div>
      </div>

      <div class="flex justify-end gap-2 mt-6">
        <button 
          @click="closeModal"
          class="px-4 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300 transition"
        >
          Batal
        </button>
        <button 
          @click="submit"
          :disabled="!form.file || isUploading"
          class="px-4 py-2 rounded bg-purple-600 text-white hover:bg-purple-700 transition disabled:opacity-50"
        >
          <span v-if="isUploading">
            <i class="fas fa-spinner fa-spin mr-2"></i>
            Importing...
          </span>
          <span v-else>
            <i class="fas fa-upload mr-2"></i>
            Import
          </span>
        </button>
      </div>
    </div>
  </div>
</template> 