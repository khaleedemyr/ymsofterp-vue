<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  outlet: Object, // untuk edit
  regions: Array,
});
const emit = defineEmits(['close', 'success']);

const form = useForm({
  nama_outlet: '',
  lokasi: '',
  region_id: '',
  status: 'A',
  qr_code: '',
  lat: '',
  long: '',
  keterangan: '',
});

watch(() => props.show, (val) => {
  if (val && props.mode === 'edit' && props.outlet) {
    form.nama_outlet = props.outlet.nama_outlet;
    form.lokasi = props.outlet.lokasi;
    form.region_id = props.outlet.region_id;
    form.status = props.outlet.status;
    form.qr_code = props.outlet.qr_code || '';
    form.lat = props.outlet.lat || '';
    form.long = props.outlet.long || '';
    form.keterangan = props.outlet.keterangan || '';
  } else if (val && props.mode === 'create') {
    form.nama_outlet = '';
    form.lokasi = '';
    form.region_id = '';
    form.status = 'A';
    form.qr_code = '';
    form.lat = '';
    form.long = '';
    form.keterangan = '';
  }
});

const isSubmitting = ref(false);

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
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto p-0 animate-fade-in">
      <div class="px-8 pt-8 pb-2">
        <div class="flex items-center gap-2 mb-6">
          <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path d="M4 6h16M4 12h16M4 18h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <h3 class="text-2xl font-bold text-gray-900">{{ mode === 'edit' ? 'Edit' : 'Tambah' }} Outlet</h3>
        </div>
        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Outlet</label>
            <input v-model="form.nama_outlet" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="100" />
            <div v-if="form.errors.nama_outlet" class="text-xs text-red-500 mt-1">{{ form.errors.nama_outlet }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Lokasi</label>
            <input v-model="form.lokasi" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="255" />
            <div v-if="form.errors.lokasi" class="text-xs text-red-500 mt-1">{{ form.errors.lokasi }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Region</label>
            <select v-model="form.region_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
              <option value="">Pilih Region</option>
              <option v-for="region in regions" :key="region.id" :value="region.id">{{ region.name }}</option>
            </select>
            <div v-if="form.errors.region_id" class="text-xs text-red-500 mt-1">{{ form.errors.region_id }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <select v-model="form.status" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
              <option value="A">Active</option>
              <option value="N">Inactive</option>
            </select>
            <div v-if="form.errors.status" class="text-xs text-red-500 mt-1">{{ form.errors.status }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">QR Code (opsional)</label>
            <input v-model="form.qr_code" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="255" placeholder="Isi manual atau kosongkan untuk auto-generate" />
            <div v-if="form.errors.qr_code" class="text-xs text-red-500 mt-1">{{ form.errors.qr_code }}</div>
          </div>
          <div class="flex gap-2">
            <div class="w-1/2">
              <label class="block text-sm font-medium text-gray-700">Latitude</label>
              <input v-model="form.lat" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="50" placeholder="-6.2xxxx" />
              <div v-if="form.errors.lat" class="text-xs text-red-500 mt-1">{{ form.errors.lat }}</div>
            </div>
            <div class="w-1/2">
              <label class="block text-sm font-medium text-gray-700">Longitude</label>
              <input v-model="form.long" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="50" placeholder="106.8xxxxx" />
              <div v-if="form.errors.long" class="text-xs text-red-500 mt-1">{{ form.errors.long }}</div>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Keterangan</label>
            <textarea v-model="form.keterangan" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" rows="2" maxlength="255" placeholder="Keterangan tambahan (opsional)"></textarea>
            <div v-if="form.errors.keterangan" class="text-xs text-red-500 mt-1">{{ form.errors.keterangan }}</div>
          </div>
          <div class="flex justify-end gap-2 pt-4">
            <button type="button" @click="closeModal" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200">Batal</button>
            <button type="submit" :disabled="isSubmitting" class="px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-60">
              {{ mode === 'edit' ? 'Update' : 'Simpan' }}
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