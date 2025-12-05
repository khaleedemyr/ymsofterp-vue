<template>
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-coins text-purple-500"></i>
          Input Saldo Karyawan
        </h2>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 transition-colors">
          <i class="fa-solid fa-times text-xl"></i>
        </button>
      </div>

      <div v-if="user" class="mb-6 p-4 bg-gray-50 rounded-lg">
        <h3 class="font-semibold text-gray-800 mb-2">Data Karyawan</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-gray-600">Nama:</span>
            <span class="font-medium ml-2">{{ user.nama_lengkap }}</span>
          </div>
          <div>
            <span class="text-gray-600">NIK:</span>
            <span class="font-medium ml-2">{{ user.nik }}</span>
          </div>
          <div>
            <span class="text-gray-600">Jabatan:</span>
            <span class="font-medium ml-2">{{ user.nama_jabatan || '-' }}</span>
          </div>
          <div>
            <span class="text-gray-600">Outlet:</span>
            <span class="font-medium ml-2">{{ user.nama_outlet || '-' }}</span>
          </div>
        </div>
      </div>

      <form @submit.prevent="submitSaldo" class="space-y-6">
        <!-- Saldo Cuti -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h4 class="font-semibold text-blue-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-calendar-days"></i>
            Saldo Cuti
          </h4>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Saldo Cuti Saat Ini</label>
              <input 
                type="number" 
                v-model="form.cuti" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Masukkan saldo cuti"
                min="0"
                step="0.5"
              />
            </div>
            <div class="flex items-end">
              <span class="text-sm text-gray-600">hari</span>
            </div>
          </div>
        </div>

        <!-- Saldo Public Holiday -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
          <h4 class="font-semibold text-green-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-calendar-check"></i>
            Saldo Public Holiday
          </h4>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Saldo PH Saat Ini</label>
              <input 
                type="number" 
                v-model="form.public_holiday" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                placeholder="Masukkan saldo public holiday"
                min="0"
                step="0.5"
              />
            </div>
            <div class="flex items-end">
              <span class="text-sm text-gray-600">hari</span>
            </div>
          </div>
        </div>

        <!-- Saldo Extra Off -->
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
          <h4 class="font-semibold text-purple-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-clock"></i>
            Saldo Extra Off
          </h4>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Saldo Extra Off Saat Ini</label>
              <input 
                type="number" 
                v-model="form.extra_off" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                placeholder="Masukkan saldo extra off"
                min="0"
                step="0.5"
              />
            </div>
            <div class="flex items-end">
              <span class="text-sm text-gray-600">hari</span>
            </div>
          </div>
        </div>

        <!-- Keterangan -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan (Opsional)</label>
          <textarea 
            v-model="form.notes" 
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
            rows="3"
            placeholder="Masukkan keterangan jika ada..."
          ></textarea>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-4 pt-4 border-t">
          <button
            type="button"
            @click="$emit('close')"
            class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
          >
            Batal
          </button>
          <button
            type="submit"
            :disabled="loading"
            class="px-6 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg transition-colors flex items-center gap-2"
          >
            <i v-if="loading" class="fa fa-spinner fa-spin"></i>
            <i v-else class="fa-solid fa-save"></i>
            {{ loading ? 'Menyimpan...' : 'Simpan Saldo' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  user: Object
});

const emit = defineEmits(['close', 'success']);

const loading = ref(false);
const form = ref({
  cuti: '',
  public_holiday: '',
  extra_off: '',
  notes: ''
});

// Load current saldo when modal opens
watch(() => props.show, (newVal) => {
  if (newVal && props.user) {
    loadCurrentSaldo();
  }
});

async function loadCurrentSaldo() {
  try {
    // Load current saldo from user data
    form.value.cuti = props.user.cuti || 0;
    
    // Load extra off balance
    const response = await fetch(`/api/users/${props.user.id}/extra-off-balance`);
    if (response.ok) {
      const data = await response.json();
      form.value.extra_off = data.balance || 0;
    }
    
    // Load public holiday balance (if exists)
    const phResponse = await fetch(`/api/users/${props.user.id}/public-holiday-balance`);
    if (phResponse.ok) {
      const phData = await phResponse.json();
      form.value.public_holiday = phData.balance || 0;
    }
  } catch (error) {
    console.error('Error loading current saldo:', error);
  }
}

async function submitSaldo() {
  if (!props.user) return;
  
  // Validation
  if (form.value.cuti === '' && form.value.public_holiday === '' && form.value.extra_off === '') {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Minimal isi salah satu saldo!',
      confirmButtonColor: '#f59e0b'
    });
    return;
  }

  loading.value = true;
  
  try {
    const response = await fetch(`/api/users/${props.user.id}/update-saldo`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        cuti: form.value.cuti,
        public_holiday: form.value.public_holiday,
        extra_off: form.value.extra_off,
        notes: form.value.notes
      })
    });

    const data = await response.json();
    
    if (response.ok && data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: data.message,
        confirmButtonColor: '#10b981'
      });
      emit('success', data.message);
      emit('close');
    } else {
      throw new Error(data.message || 'Gagal menyimpan saldo');
    }
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.message,
      confirmButtonColor: '#ef4444'
    });
  } finally {
    loading.value = false;
  }
}
</script>
