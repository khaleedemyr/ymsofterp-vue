<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 transition-all">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-hidden animate-fade-in">
      <!-- Header -->
      <div class="bg-gradient-to-r from-blue-500 to-blue-700 px-6 py-4 text-white">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <i class="fa-solid fa-key text-2xl"></i>
            <h3 class="text-xl font-bold">Kelola PIN Outlet</h3>
          </div>
          <button @click="closeModal" class="text-white hover:text-gray-200 transition-colors">
            <i class="fa fa-times text-xl"></i>
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
        <!-- Add New PIN Form -->
        <div class="mb-8">
          <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-plus text-blue-500"></i>
            Tambah PIN Baru
          </h4>
          
          <form @submit.prevent="addPin" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Outlet Selection -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Pilih Outlet <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="newPin.outlet_id"
                  required
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  :disabled="loading"
                >
                  <option value="">Pilih Outlet</option>
                  <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                    {{ outlet.nama_outlet }}
                  </option>
                </select>
              </div>

              <!-- PIN Input -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  PIN <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="newPin.pin"
                  type="text"
                  required
                  minlength="1"
                  maxlength="10"
                  placeholder="Masukkan PIN (1-10 karakter)"
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  :disabled="loading"
                />
              </div>
            </div>

            <button
              type="submit"
              :disabled="loading || !newPin.outlet_id || !newPin.pin"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i v-if="loading" class="fa fa-spinner fa-spin mr-2"></i>
              <i v-else class="fa fa-plus mr-2"></i>
              Tambah PIN
            </button>
          </form>
        </div>

        <!-- Existing PINs List -->
        <div>
          <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-list text-blue-500"></i>
            PIN yang Sudah Ada
          </h4>

          <!-- Loading State -->
          <div v-if="loadingPins" class="text-center py-8">
            <i class="fa fa-spinner fa-spin text-2xl text-blue-500 mb-2"></i>
            <p class="text-gray-500">Memuat data PIN...</p>
          </div>

          <!-- Empty State -->
          <div v-else-if="userPins.length === 0" class="text-center py-8">
            <i class="fa-solid fa-key text-4xl text-gray-300 mb-4"></i>
            <p class="text-gray-500">Belum ada PIN yang dibuat</p>
          </div>

          <!-- PINs List -->
          <div v-else class="space-y-3">
            <div
              v-for="pin in userPins"
              :key="pin.id"
              class="bg-gray-50 rounded-lg p-4 border hover:shadow-md transition-shadow"
            >
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-3 mb-2">
                    <i class="fa-solid fa-store text-blue-500"></i>
                    <h5 class="font-semibold text-gray-800">{{ pin.nama_outlet }}</h5>
                    <span :class="pin.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                          class="px-2 py-1 rounded-full text-xs font-medium">
                      {{ pin.is_active ? 'Aktif' : 'Tidak Aktif' }}
                    </span>
                  </div>
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-key text-gray-400"></i>
                    <span class="font-mono text-lg font-bold text-gray-700">{{ pin.pin }}</span>
                  </div>
                  <p class="text-xs text-gray-500 mt-1">
                    Dibuat: {{ formatDate(pin.created_at) }}
                  </p>
                </div>
                
                <div class="flex gap-2">
                  <button
                    @click="editPin(pin)"
                    class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-lg hover:bg-yellow-200 transition-colors text-sm font-medium"
                  >
                    <i class="fa fa-edit mr-1"></i>
                    Edit
                  </button>
                  <button
                    @click="deletePin(pin.id)"
                    class="px-3 py-1 bg-red-100 text-red-800 rounded-lg hover:bg-red-200 transition-colors text-sm font-medium"
                  >
                    <i class="fa fa-trash mr-1"></i>
                    Hapus
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="bg-gray-50 px-6 py-4 border-t">
        <div class="flex justify-end">
          <button @click="closeModal"
                  class="px-6 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-all font-medium">
            <i class="fa fa-times mr-2"></i>Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  show: Boolean,
})

const emit = defineEmits(['close'])

// State
const loading = ref(false)
const loadingPins = ref(false)
const outlets = ref([])
const userPins = ref([])
const newPin = ref({
  outlet_id: '',
  pin: ''
})

// Fetch outlets
const fetchOutlets = async () => {
  try {
    const response = await axios.get('/api/outlets')
    outlets.value = response.data
  } catch (error) {
    console.error('Error fetching outlets:', error)
    Swal.fire({
      title: 'Error',
      text: 'Gagal memuat data outlet',
      icon: 'error'
    })
  }
}

// Fetch user pins
const fetchUserPins = async () => {
  loadingPins.value = true
  try {
    const response = await axios.get('/api/user-pins')
    userPins.value = response.data
  } catch (error) {
    console.error('Error fetching user pins:', error)
    Swal.fire({
      title: 'Error',
      text: 'Gagal memuat data PIN',
      icon: 'error'
    })
  } finally {
    loadingPins.value = false
  }
}

// Add new PIN
const addPin = async () => {
  loading.value = true
  try {
    const response = await axios.post('/api/user-pins', newPin.value)
    
    if (response.data.success) {
      Swal.fire({
        title: 'Berhasil!',
        text: response.data.message,
        icon: 'success'
      })
      
      // Reset form
      newPin.value = {
        outlet_id: '',
        pin: ''
      }
      
      // Refresh pins list
      await fetchUserPins()
    } else {
      Swal.fire({
        title: 'Gagal!',
        text: response.data.message,
        icon: 'error'
      })
    }
  } catch (error) {
    console.error('Error adding pin:', error)
    const message = error.response?.data?.message || 'Gagal menambah PIN'
    Swal.fire({
      title: 'Error',
      text: message,
      icon: 'error'
    })
  } finally {
    loading.value = false
  }
}

// Edit PIN
const editPin = async (pin) => {
  const { value: newPinValue } = await Swal.fire({
    title: 'Edit PIN',
    text: `Outlet: ${pin.nama_outlet}`,
    input: 'text',
    inputValue: pin.pin,
    inputPlaceholder: 'Masukkan PIN baru',
    inputValidator: (value) => {
      if (!value || value.length < 1 || value.length > 10) {
        return 'PIN harus 1-10 karakter'
      }
    },
    showCancelButton: true,
    confirmButtonText: 'Update',
    cancelButtonText: 'Batal'
  })

  if (newPinValue) {
    try {
      const response = await axios.put(`/api/user-pins/${pin.id}`, {
        pin: newPinValue
      })
      
      if (response.data.success) {
        Swal.fire({
          title: 'Berhasil!',
          text: response.data.message,
          icon: 'success'
        })
        
        // Refresh pins list
        await fetchUserPins()
      } else {
        Swal.fire({
          title: 'Gagal!',
          text: response.data.message,
          icon: 'error'
        })
      }
    } catch (error) {
      console.error('Error updating pin:', error)
      const message = error.response?.data?.message || 'Gagal mengupdate PIN'
      Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error'
      })
    }
  }
}

// Delete PIN
const deletePin = async (pinId) => {
  const result = await Swal.fire({
    title: 'Hapus PIN?',
    text: 'Apakah Anda yakin ingin menghapus PIN ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  })

  if (result.isConfirmed) {
    try {
      const response = await axios.delete(`/api/user-pins/${pinId}`)
      
      if (response.data.success) {
        Swal.fire({
          title: 'Berhasil!',
          text: response.data.message,
          icon: 'success'
        })
        
        // Refresh pins list
        await fetchUserPins()
      } else {
        Swal.fire({
          title: 'Gagal!',
          text: response.data.message,
          icon: 'error'
        })
      }
    } catch (error) {
      console.error('Error deleting pin:', error)
      const message = error.response?.data?.message || 'Gagal menghapus PIN'
      Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error'
      })
    }
  }
}

// Format date
const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleDateString('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Close modal
const closeModal = () => {
  emit('close')
}

// Watch for modal show
watch(() => props.show, (newVal) => {
  if (newVal) {
    fetchOutlets()
    fetchUserPins()
  }
})

onMounted(() => {
  if (props.show) {
    fetchOutlets()
    fetchUserPins()
  }
})
</script>

<style scoped>
@keyframes fade-in {
  from { 
    opacity: 0; 
    transform: translateY(20px) scale(0.95);
  }
  to { 
    opacity: 1; 
    transform: translateY(0) scale(1);
  }
}
.animate-fade-in {
  animation: fade-in 0.3s cubic-bezier(.4,0,.2,1);
}
</style>
