<script setup>
import { ref, watch, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  user: Object, // untuk edit
  dropdownData: Object, // { outlets, jabatans }
  isLoadingDropdown: Boolean,
});
const emit = defineEmits(['close', 'success']);

const form = useForm({
  nik: '',
  nama_lengkap: '',
  email: '',
  no_hp: '',
  id_outlet: '',
  id_jabatan: '',
  status: 'A',
});

watch(() => props.show, (val) => {
  if (val) {
    if (props.mode === 'edit' && props.user) {
      form.nik = props.user.nik || '';
      form.nama_lengkap = props.user.nama_lengkap || '';
      form.email = props.user.email || '';
      form.no_hp = props.user.no_hp || '';
      form.id_outlet = props.user.id_outlet || '';
      form.id_jabatan = props.user.id_jabatan || '';
      form.status = props.user.status || 'A';
    } else {
      form.nik = '';
      form.nama_lengkap = '';
      form.email = '';
      form.no_hp = '';
      form.id_outlet = '';
      form.id_jabatan = '';
      form.status = 'A';
    }
  }
});

const isSubmitting = ref(false);

async function submit() {
  isSubmitting.value = true;
  if (props.mode === 'create') {
    form.post(route('users.store'), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Karyawan berhasil ditambahkan!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => isSubmitting.value = false,
      onFinish: () => isSubmitting.value = false,
    });
  } else if (props.mode === 'edit' && props.user) {
    form.put(route('users.update', props.user.id), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Karyawan berhasil diupdate!', 'success');
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
            <path d="M16 7a4 4 0 01-8 0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 3v4m0 0a4 4 0 01-4 4H4m8-4a4 4 0 014 4h4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <h3 class="text-2xl font-bold text-gray-900">{{ mode === 'edit' ? 'Edit' : 'Tambah' }} Karyawan</h3>
        </div>
        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700">NIK</label>
            <input v-model="form.nik" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="50" />
            <div v-if="form.errors.nik" class="text-xs text-red-500 mt-1">{{ form.errors.nik }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
            <input v-model="form.nama_lengkap" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="255" />
            <div v-if="form.errors.nama_lengkap" class="text-xs text-red-500 mt-1">{{ form.errors.nama_lengkap }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Email</label>
            <input v-model="form.email" type="email" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="255" />
            <div v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">No HP</label>
            <input v-model="form.no_hp" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="15" />
            <div v-if="form.errors.no_hp" class="text-xs text-red-500 mt-1">{{ form.errors.no_hp }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Outlet</label>
            <select v-model="form.id_outlet" :disabled="isLoadingDropdown" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in dropdownData.outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">{{ outlet.nama_outlet }}</option>
            </select>
            <div v-if="form.errors.id_outlet" class="text-xs text-red-500 mt-1">{{ form.errors.id_outlet }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Jabatan</label>
            <select v-model="form.id_jabatan" :disabled="isLoadingDropdown" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
              <option value="">Pilih Jabatan</option>
              <option v-for="jabatan in dropdownData.jabatans" :key="jabatan.id_jabatan" :value="jabatan.id_jabatan">{{ jabatan.nama_jabatan }}</option>
            </select>
            <div v-if="form.errors.id_jabatan" class="text-xs text-red-500 mt-1">{{ form.errors.id_jabatan }}</div>
          </div>
          <input type="hidden" v-model="form.status" />
        </form>
      </div>
      <div class="bg-gray-50 px-6 py-4 flex justify-end rounded-b-2xl">
        <button type="button" @click="closeModal" class="inline-flex items-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition sm:w-auto sm:text-sm mr-2">
          Batal
        </button>
        <button type="button" @click="submit" :disabled="isSubmitting" class="inline-flex items-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
          <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ isSubmitting ? (mode === 'edit' ? 'Menyimpan...' : 'Menambah...') : (mode === 'edit' ? 'Simpan' : 'Tambah') }}
        </button>
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