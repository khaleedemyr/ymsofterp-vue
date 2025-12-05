<template>
  <Modal :show="show" @close="close">
    <div class="p-6">
      <h2 class="text-lg font-medium text-gray-900">
        Import Supplier
      </h2>

      <div class="mt-6">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center space-x-4">
            <button
              @click="downloadTemplate"
              :disabled="isDownloading"
              class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg v-if="isDownloading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
              Download Template
            </button>
            <a 
              href="#" 
              @click.prevent="showInstructions = true"
              class="text-sm text-blue-600 hover:text-blue-800"
            >
              Lihat Panduan Import
            </a>
          </div>
        </div>

        <div v-if="error" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm text-red-700">{{ error }}</p>
            </div>
          </div>
        </div>

        <div v-if="!previewData" class="mt-4">
          <div class="flex items-center justify-center w-full">
            <label
              class="flex flex-col items-center justify-center w-full h-64 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100"
            >
              <div class="flex flex-col items-center justify-center pt-5 pb-6">
                <svg
                  class="w-8 h-8 mb-4 text-gray-500"
                  aria-hidden="true"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 20 16"
                >
                  <path
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"
                  />
                </svg>
                <p class="mb-2 text-sm text-gray-500">
                  <span class="font-semibold">Click to upload</span> or drag and drop
                </p>
                <p class="text-xs text-gray-500">XLSX or XLS</p>
              </div>
              <input
                type="file"
                class="hidden"
                accept=".xlsx,.xls"
                @change="handleFileUpload"
                :disabled="isUploading"
              />
            </label>
          </div>
        </div>

        <div v-else class="mt-4">
          <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-4 bg-gray-50 border-b">
              <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Preview Data</h3>
                <div class="text-sm text-gray-500">
                  Total: {{ previewData.total_rows }} | Valid: {{ previewData.valid_rows }} | Invalid: {{ previewData.invalid_rows }}
                </div>
              </div>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Row</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Error</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="row in previewData.preview" :key="row.row_number">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ row.row_number }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ row.data.name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ row.data.email }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ row.data.phone }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span
                        :class="[
                          row.is_valid
                            ? 'bg-green-100 text-green-800'
                            : 'bg-red-100 text-red-800',
                          'px-2 inline-flex text-xs leading-5 font-semibold rounded-full'
                        ]"
                      >
                        {{ row.is_valid ? 'Valid' : 'Invalid' }}
                      </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-red-600">
                      {{ row.errors.join(', ') }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="p-4 bg-gray-50 border-t">
              <div class="flex justify-end space-x-3">
                <button
                  @click="resetPreview"
                  :disabled="isImporting"
                  class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
                >
                  Cancel
                </button>
                <button
                  @click="importData"
                  :disabled="previewData.invalid_rows > 0 || isImporting"
                  class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                >
                  <svg v-if="isImporting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Import Data
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Instructions Modal -->
    <Modal :show="showInstructions" @close="showInstructions = false">
      <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Panduan Import Supplier</h3>
        <div class="space-y-4 text-sm text-gray-600">
          <p>1. Download template Excel dengan mengklik tombol "Download Template"</p>
          <p>2. Isi data supplier pada sheet "Data" sesuai dengan kolom yang tersedia:</p>
          <ul class="list-disc pl-5 space-y-2">
            <li>name: Nama supplier (wajib)</li>
            <li>contact_person: Nama kontak (opsional)</li>
            <li>phone: Nomor telepon (opsional)</li>
            <li>email: Email (opsional, harus unik)</li>
            <li>address: Alamat (opsional)</li>
            <li>city: Kota (opsional)</li>
            <li>province: Provinsi (opsional)</li>
            <li>postal_code: Kode pos (opsional)</li>
            <li>npwp: Nomor NPWP (opsional)</li>
            <li>bank_name: Nama bank (opsional)</li>
            <li>bank_account_number: Nomor rekening (opsional)</li>
            <li>bank_account_name: Nama pemilik rekening (opsional)</li>
            <li>payment_term: Syarat pembayaran (opsional)</li>
            <li>payment_days: Jumlah hari pembayaran (opsional, harus angka)</li>
          </ul>
          <p>3. Upload file Excel yang sudah diisi</p>
          <p>4. Periksa preview data untuk memastikan semua data valid</p>
          <p>5. Klik "Import Data" untuk menyimpan data</p>
        </div>
      </div>
    </Modal>
  </Modal>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import { useForm } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
  show: Boolean
})

const emit = defineEmits(['close'])

const previewData = ref(null)
const error = ref(null)
const isDownloading = ref(false)
const isUploading = ref(false)
const isImporting = ref(false)
const showInstructions = ref(false)

const form = useForm({
  file: null
})

const close = () => {
  emit('close')
  resetPreview()
}

const resetPreview = () => {
  previewData.value = null
  error.value = null
  form.reset()
}

const downloadTemplate = async () => {
  isDownloading.value = true
  error.value = null
  try {
    window.location.href = route('suppliers.import.template')
  } catch (err) {
    error.value = 'Gagal mengunduh template. Silakan coba lagi.'
  } finally {
    isDownloading.value = false
  }
}

const handleFileUpload = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  error.value = null
  isUploading.value = true
  form.file = file

  try {
    const response = await axios.post(route('suppliers.import.preview'), form, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    previewData.value = response.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal memproses file. Silakan coba lagi.'
    previewData.value = null
  } finally {
    isUploading.value = false
  }
}

const importData = async () => {
  if (!form.file) return

  error.value = null
  isImporting.value = true

  try {
    const response = await axios.post(route('suppliers.import.excel'), form, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })

    // Show success message with SweetAlert
    await Swal.fire({
      title: 'Import Completed!',
      html: `<b>Success:</b> ${response.data.success_count}<br><b>Failed:</b> ${response.data.error_count}`,
      icon: response.data.error_count > 0 ? 'warning' : 'success',
      confirmButtonText: 'OK',
    })

    // Close modal and refresh page
    close()
    router.reload()
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal mengimport data. Silakan coba lagi.'
  } finally {
    isImporting.value = false
  }
}
</script> 