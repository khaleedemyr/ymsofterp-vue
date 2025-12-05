<script setup>
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  jabatan: Object, // untuk edit
  dropdownData: Object, // { jabatans, divisis, subDivisis, levels }
  isLoadingDropdown: Boolean, // Add loading state prop
});
const emit = defineEmits(['close', 'success']);

const form = useForm({
  nama_jabatan: '',
  id_atasan: '',
  id_divisi: '',
  id_sub_divisi: '',
  id_level: '',
  status: 'A',
});

watch(() => props.show, (val) => {
  if (val && props.mode === 'edit' && props.jabatan) {
    form.nama_jabatan = props.jabatan.nama_jabatan;
    form.id_atasan = props.jabatan.id_atasan || '';
    form.id_divisi = props.jabatan.id_divisi || '';
    form.id_sub_divisi = props.jabatan.id_sub_divisi || '';
    form.id_level = props.jabatan.id_level || '';
    // Don't change status in edit mode
  } else if (val && props.mode === 'create') {
    form.nama_jabatan = '';
    form.id_atasan = '';
    form.id_divisi = '';
    form.id_sub_divisi = '';
    form.id_level = '';
    form.status = 'A'; // Always set to active for new records
  }
});

const isSubmitting = ref(false);

// Add computed property to check if dropdown data is ready
const isDropdownReady = computed(() => {
  return props.dropdownData && 
         Array.isArray(props.dropdownData.jabatans) && 
         Array.isArray(props.dropdownData.divisis) && 
         Array.isArray(props.dropdownData.subDivisis) && 
         Array.isArray(props.dropdownData.levels);
});

// Add watcher to monitor dropdown data changes
watch(() => props.dropdownData, (newData) => {
  console.log('Modal dropdown data changed:', newData);
  console.log('Is dropdown ready:', isDropdownReady.value);
}, { deep: true });

// Add watcher to monitor loading state
watch(() => props.isLoadingDropdown, (loading) => {
  console.log('Modal loading state changed:', loading);
});

async function submit() {
  isSubmitting.value = true;
  if (props.mode === 'create') {
    form.post(route('jabatans.store'), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Jabatan berhasil ditambahkan!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => isSubmitting.value = false,
      onFinish: () => isSubmitting.value = false,
    });
  } else if (props.mode === 'edit' && props.jabatan) {
    form._method = 'PUT';
    form.post(route('jabatans.update', props.jabatan.id_jabatan), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Jabatan berhasil diupdate!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => isSubmitting.value = false,
      onFinish: () => isSubmitting.value = false,
    });
  }
}

function closeModal() {
  emit('close');
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 transition-all">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-auto p-0 animate-fade-in">
      <div class="px-8 pt-8 pb-2">
        <div class="flex items-center gap-2 mb-6">
          <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path d="M16 7a4 4 0 01-8 0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 3v4m0 0a4 4 0 01-4 4H4m8-4a4 4 0 014 4h4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <h3 class="text-2xl font-bold text-gray-900">{{ mode === 'edit' ? 'Edit' : 'Tambah' }} Jabatan</h3>
        </div>
        <form @submit.prevent="submit" class="space-y-5">
          <!-- Loading indicator -->
          <div v-if="isLoadingDropdown" class="text-center py-4">
            <div class="inline-flex items-center px-4 py-2 font-semibold leading-6 text-blue-600 transition ease-in-out duration-150">
              <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Memuat data dropdown...
            </div>
          </div>
          
          <!-- Form fields (disabled when loading) -->
          <div :class="{ 'opacity-50 pointer-events-none': isLoadingDropdown || !isDropdownReady }">
            <div>
              <label class="block text-sm font-medium text-gray-700">Nama Jabatan</label>
              <input v-model="form.nama_jabatan" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="100" />
              <div v-if="form.errors.nama_jabatan" class="text-xs text-red-500 mt-1">{{ form.errors.nama_jabatan }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Atasan</label>
              <select v-model="form.id_atasan" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Pilih Atasan (Opsional)</option>
                <option v-for="jabatan in dropdownData.jabatans" :key="jabatan.id_jabatan" :value="jabatan.id_jabatan">
                  {{ jabatan.nama_jabatan }}
                </option>
              </select>
              <div v-if="form.errors.id_atasan" class="text-xs text-red-500 mt-1">{{ form.errors.id_atasan }}</div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Divisi</label>
                <select v-model="form.id_divisi" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                  <option value="">Pilih Divisi</option>
                  <option v-for="divisi in dropdownData.divisis" :key="divisi.id" :value="divisi.id">
                    {{ divisi.nama_divisi }}
                  </option>
                </select>
                <div v-if="form.errors.id_divisi" class="text-xs text-red-500 mt-1">{{ form.errors.id_divisi }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Sub Divisi</label>
                <select v-model="form.id_sub_divisi" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                  <option value="">Pilih Sub Divisi</option>
                  <option v-for="subDivisi in dropdownData.subDivisis" :key="subDivisi.id" :value="subDivisi.id">
                    {{ subDivisi.nama_sub_divisi }}
                  </option>
                </select>
                <div v-if="form.errors.id_sub_divisi" class="text-xs text-red-500 mt-1">{{ form.errors.id_sub_divisi }}</div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Level</label>
              <select v-model="form.id_level" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                <option value="">Pilih Level</option>
                <option v-for="level in dropdownData.levels" :key="level.id" :value="level.id">
                  {{ level.nama_level }}
                </option>
              </select>
              <div v-if="form.errors.id_level" class="text-xs text-red-500 mt-1">{{ form.errors.id_level }}</div>
            </div>
          </div>
          <!-- Tombol Batal dan Simpan -->
          <div class="bg-gray-50 px-6 py-4 flex justify-end rounded-b-2xl">
            <button type="button" @click="closeModal" class="inline-flex items-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition sm:w-auto sm:text-sm mr-2">
              Batal
            </button>
            <button 
              type="submit" 
              :disabled="isSubmitting || isLoadingDropdown || !isDropdownReady"
              class="inline-flex items-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ isSubmitting ? 'Menyimpan...' : (mode === 'edit' ? 'Update' : 'Simpan') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(20px);}
  to { opacity: 1; transform: translateY(0);}
}
.animate-fade-in {
  animation: fade-in 0.3s cubic-bezier(.4,0,.2,1);
}
</style> 