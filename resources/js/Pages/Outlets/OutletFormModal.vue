<script setup>
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import PickLocationMapModal from './PickLocationMapModal.vue';

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  outlet: Object, // untuk edit
  dropdownData: Object,
  isLoadingDropdown: Boolean,
});
const emit = defineEmits(['close', 'success']);

const form = useForm({
  nama_outlet: '',
  lokasi: '',
  qr_code: '',
  lat: '',
  long: '',
  keterangan: '',
  region_id: '',
  status: 'A',
  url_places: '',
  sn: '',
  activation_code: '',
});

watch(() => props.show, (val) => {
  if (val && props.mode === 'edit' && props.outlet) {
    form.nama_outlet = props.outlet.nama_outlet;
    form.lokasi = props.outlet.lokasi;
    form.qr_code = props.outlet.qr_code || '';
    form.lat = props.outlet.lat || '';
    form.long = props.outlet.long || '';
    form.keterangan = props.outlet.keterangan || '';
    form.region_id = props.outlet.region_id;
    form.status = props.outlet.status;
    form.url_places = props.outlet.url_places || '';
    form.sn = props.outlet.sn || '';
    form.activation_code = props.outlet.activation_code || '';
  } else if (val && props.mode === 'create') {
    form.nama_outlet = '';
    form.lokasi = '';
    form.qr_code = '';
    form.lat = '';
    form.long = '';
    form.keterangan = '';
    form.region_id = '';
    form.status = 'A';
    form.url_places = '';
    form.sn = '';
    form.activation_code = '';
  }
});

const isSubmitting = ref(false);

// Computed property untuk mengecek apakah dropdown sudah siap
const isDropdownReady = computed(() => {
  return !props.isLoadingDropdown && props.dropdownData && props.dropdownData.regions;
});

const showPickMap = ref(false);

function handlePickLocation({ lat, long, alamat }) {
  form.lat = lat;
  form.long = long;
  if (alamat) {
    form.lokasi = alamat;
  }
}

async function submit() {
  isSubmitting.value = true;
  if (props.mode === 'create') {
    form.post(route('outlets.store'), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Outlet berhasil ditambahkan!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => isSubmitting.value = false,
      onFinish: () => isSubmitting.value = false,
    });
  } else if (props.mode === 'edit' && props.outlet) {
    form._method = 'PUT';
    form.post(route('outlets.update', props.outlet.id_outlet), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Outlet berhasil diupdate!', 'success');
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
            <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <h3 class="text-2xl font-bold text-gray-900">{{ mode === 'edit' ? 'Edit' : 'Tambah' }} Outlet</h3>
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
              <label class="block text-sm font-medium text-gray-700">Nama Outlet</label>
              <input v-model="form.nama_outlet" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="100" />
              <div v-if="form.errors.nama_outlet" class="text-xs text-red-500 mt-1">{{ form.errors.nama_outlet }}</div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">Lokasi</label>
              <textarea v-model="form.lokasi" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" rows="3" required placeholder="Alamat lengkap outlet"></textarea>
              <div v-if="form.errors.lokasi" class="text-xs text-red-500 mt-1">{{ form.errors.lokasi }}</div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">Region</label>
              <select v-model="form.region_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                <option value="">Pilih Region</option>
                <option v-for="region in dropdownData.regions" :key="region.id" :value="region.id">
                  {{ region.name }}
                </option>
              </select>
              <div v-if="form.errors.region_id" class="text-xs text-red-500 mt-1">{{ form.errors.region_id }}</div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">QR Code (opsional)</label>
              <input v-model="form.qr_code" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="255" placeholder="Isi manual atau kosongkan untuk auto-generate" />
              <div v-if="form.errors.qr_code" class="text-xs text-red-500 mt-1">{{ form.errors.qr_code }}</div>
            </div>
            
            <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium mb-1">Latitude</label>
                <input v-model="form.lat" type="text" class="form-input w-full" placeholder="Latitude" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Longitude</label>
                <input v-model="form.long" type="text" class="form-input w-full" placeholder="Longitude" />
              </div>
              <div class="col-span-2 flex items-center gap-2 mt-1">
                <button type="button" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm" @click="showPickMap = true">
                  <i class="fas fa-map-marked-alt"></i> Pilih dari Peta
                </button>
                <span class="text-xs text-gray-400">(Klik peta untuk mengisi lat/long)</span>
              </div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">URL Google Places (opsional)</label>
              <input v-model="form.url_places" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" type="url" placeholder="https://maps.google.com/..." />
              <div v-if="form.errors.url_places" class="text-xs text-red-500 mt-1">{{ form.errors.url_places }}</div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">Keterangan</label>
              <textarea v-model="form.keterangan" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" rows="3" placeholder="Keterangan tambahan (opsional)"></textarea>
              <div v-if="form.errors.keterangan" class="text-xs text-red-500 mt-1">{{ form.errors.keterangan }}</div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">Serial Number (SN)</label>
              <input v-model="form.sn" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="100" placeholder="Serial Number" />
              <div v-if="form.errors.sn" class="text-xs text-red-500 mt-1">{{ form.errors.sn }}</div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">Activation Code</label>
              <input v-model="form.activation_code" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="100" placeholder="Activation Code" />
              <div v-if="form.errors.activation_code" class="text-xs text-red-500 mt-1">{{ form.errors.activation_code }}</div>
            </div>
          </div>
          
          <div class="flex justify-end gap-2 pt-4">
            <button type="button" @click="closeModal" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200">Batal</button>
            <button type="submit" :disabled="isSubmitting || isLoadingDropdown || !isDropdownReady" class="px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-60">
              {{ mode === 'edit' ? 'Update' : 'Simpan' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <PickLocationMapModal
    :show="showPickMap"
    :lat="form.lat"
    :long="form.long"
    @close="showPickMap = false"
    @picked="handlePickLocation"
  />
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