<script setup>
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  userId: [String, Number],
  userName: String,
});
const emit = defineEmits(['close']);

const pins = ref([]);
const outlets = ref([]);
const isLoading = ref(false);
const isFormOpen = ref(false);
const isEdit = ref(false);
const form = ref({ id: null, outlet_id: '', pin: '', is_active: true });

function fetchPins() {
  isLoading.value = true;
  axios.get(`/admin/users/${props.userId}/pins`).then(res => {
    pins.value = res.data.pins || [];
    outlets.value = res.data.outlets || [];
  }).catch(err => {
    console.error('Error fetching pins:', err);
    pins.value = [];
    outlets.value = [];
    
    let errorMessage = 'Gagal memuat data PIN';
    if (err.response?.status === 401) {
      errorMessage = 'Anda harus login untuk mengakses data ini';
    } else if (err.response?.status === 403) {
      errorMessage = 'Anda tidak memiliki akses untuk mengelola PIN';
    } else if (err.response?.status === 404) {
      errorMessage = 'Data PIN tidak ditemukan';
    } else if (err.response?.data?.message) {
      errorMessage = err.response.data.message;
    }
    
    Swal.fire('Error', errorMessage, 'error');
  }).finally(() => {
    isLoading.value = false;
  });
}

watch(() => props.show, (val) => {
  if (val) fetchPins();
});

function openAddForm() {
  isEdit.value = false;
  form.value = { id: null, outlet_id: '', pin: '', is_active: true };
  isFormOpen.value = true;
}

function openEditForm(pin) {
  isEdit.value = true;
  form.value = { ...pin, outlet_id: pin.outlet_id, is_active: !!pin.is_active };
  isFormOpen.value = true;
}

function closeForm() {
  isFormOpen.value = false;
}

function savePin() {
  isLoading.value = true;
  const payload = {
    outlet_id: form.value.outlet_id,
    pin: form.value.pin,
    is_active: form.value.is_active ? 1 : 0,
  };
  if (isEdit.value && form.value.id) {
    axios.put(`/admin/user-pins/${form.value.id}`, payload)
      .then(() => {
        Swal.fire('Berhasil', 'PIN berhasil diupdate', 'success');
        fetchPins();
        closeForm();
      })
      .catch(err => {
        Swal.fire('Gagal', err.response?.data?.message || 'Gagal update PIN', 'error');
      })
      .finally(() => isLoading.value = false);
  } else {
    axios.post(`/admin/users/${props.userId}/pins`, payload)
      .then(() => {
        Swal.fire('Berhasil', 'PIN berhasil ditambahkan', 'success');
        fetchPins();
        closeForm();
      })
      .catch(err => {
        Swal.fire('Gagal', err.response?.data?.message || 'Gagal tambah PIN', 'error');
      })
      .finally(() => isLoading.value = false);
  }
}

function deletePin(id) {
  Swal.fire({
    title: 'Hapus PIN?',
    text: 'PIN akan dihapus permanen',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
  }).then(result => {
    if (result.isConfirmed) {
      isLoading.value = true;
      axios.delete(`/admin/user-pins/${id}`)
        .then(() => {
          Swal.fire('Berhasil', 'PIN dihapus', 'success');
          fetchPins();
        })
        .catch(() => {
          Swal.fire('Gagal', 'Gagal hapus PIN', 'error');
        })
        .finally(() => isLoading.value = false);
    }
  });
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
          <i class="fa-solid fa-key text-blue-500 text-2xl"></i>
          <h3 class="text-2xl font-bold text-gray-900">Kelola PIN - {{ userName }}</h3>
        </div>
        <div class="mb-4 flex justify-between items-center">
          <button @click="openAddForm" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 flex items-center gap-2">
            <i class="fa-solid fa-plus"></i> Tambah PIN
          </button>
          <button @click="closeModal" class="bg-gray-100 text-gray-700 px-4 py-2 rounded hover:bg-gray-200 flex items-center gap-2">
            <i class="fa-solid fa-xmark"></i> Tutup
          </button>
        </div>
        <div v-if="isLoading" class="text-center py-4">
          <i class="fa fa-spinner fa-spin text-blue-600 text-2xl"></i> Memuat data...
        </div>
        <div v-else>
          <table class="w-full border rounded mb-4">
            <thead>
              <tr class="bg-gray-100">
                <th class="py-2 px-3 text-left">Outlet</th>
                <th class="py-2 px-3 text-left">PIN</th>
                <th class="py-2 px-3 text-left">Status</th>
                <th class="py-2 px-3 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="pin in (pins || [])" :key="pin.id" class="border-b">
                <td class="py-2 px-3">{{ pin.nama_outlet || '-' }}</td>
                <td class="py-2 px-3 font-mono">{{ pin.pin }}</td>
                <td class="py-2 px-3">
                  <span :class="pin.is_active ? 'text-green-600' : 'text-gray-400'">
                    {{ pin.is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </td>
                <td class="py-2 px-3 text-center">
                  <button @click="openEditForm(pin)" class="text-blue-600 hover:underline mr-2"><i class="fa fa-edit"></i> Edit</button>
                  <button @click="deletePin(pin.id)" class="text-red-600 hover:underline"><i class="fa fa-trash"></i> Hapus</button>
                </td>
              </tr>
              <tr v-if="!pins || pins.length === 0">
                <td colspan="4" class="text-center text-gray-400 py-4">Belum ada PIN</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Form Tambah/Edit PIN -->
        <div v-if="isFormOpen" class="border-t pt-4 mt-4">
          <h4 class="font-semibold mb-2">{{ isEdit ? 'Edit' : 'Tambah' }} PIN</h4>
          <form @submit.prevent="savePin" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Outlet</label>
              <select v-model="form.outlet_id" class="form-input w-full" required>
                <option value="">Pilih Outlet</option>
                <option v-for="outlet in (outlets || [])" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">PIN</label>
              <input v-model="form.pin" type="text" maxlength="20" class="form-input w-full font-mono" required />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Status</label>
              <select v-model="form.is_active" class="form-input w-full">
                <option :value="true">Aktif</option>
                <option :value="false">Nonaktif</option>
              </select>
            </div>
            <div class="flex items-end gap-2">
              <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Simpan</button>
              <button type="button" @click="closeForm" class="bg-gray-100 text-gray-700 px-4 py-2 rounded hover:bg-gray-200">Batal</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template> 