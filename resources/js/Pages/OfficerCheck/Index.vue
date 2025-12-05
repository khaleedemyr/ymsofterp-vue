<template>
  <AppLayout>
    <div class="max-w-5xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-user-check text-blue-500"></i> Officer Check
        </h1>
        <button @click="openAddForm" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <i class="fa-solid fa-plus"></i>
          Tambah OC
        </button>
      </div>
      <!-- Form Inline -->
      <div v-if="showFormInline" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-40 transition-all">
        <div class="max-w-lg mx-auto py-8 px-2 bg-white rounded-2xl shadow-2xl">
          <h2 class="text-2xl font-bold text-blue-700 mb-6">{{ editMode ? 'Edit Officer Check' : 'Tambah Officer Check' }}</h2>
          <form @submit.prevent="saveOC" class="space-y-5 bg-white rounded-2xl shadow-xl p-8">
            <div>
              <label class="block text-sm font-medium text-gray-700">User</label>
              <Multiselect v-model="form.user_id" :options="users" :searchable="true" label="name" track-by="id" placeholder="Pilih User" class="mt-1" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Nilai OC</label>
              <input v-model="form.nilai" type="number" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required />
            </div>
            <div class="flex justify-end gap-2 mt-8">
              <button type="button" @click="closeFormInline" class="px-4 py-2 rounded bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200">Batal</button>
              <button type="submit" :disabled="isSubmitting" class="px-6 py-2 rounded bg-blue-600 text-white font-bold hover:bg-blue-700">
                <span v-if="isSubmitting">
                  <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Menyimpan...
                </span>
                <span v-else>
                  Simpan
                </span>
              </button>
            </div>
          </form>
        </div>
      </div>
      <!-- List OC -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden mt-8">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nilai OC</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="ocs.length === 0">
              <td colspan="3" class="text-center py-10 text-gray-400">Tidak ada data Officer Check.</td>
            </tr>
            <tr v-for="oc in ocs" :key="oc.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">{{ oc.user_name }}</td>
              <td class="px-6 py-4 whitespace-nowrap">{{ oc.nilai }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <button class="px-2 py-1 bg-yellow-400 rounded mr-2" @click="openEditForm(oc)">Edit</button>
                <button class="px-2 py-1 bg-red-500 text-white rounded" @click="deleteOC(oc.id)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const ocs = ref([]);
const users = ref([]);
const showFormInline = ref(false);
const editMode = ref(false);
const isSubmitting = ref(false);
const form = ref({ id: null, user_id: null, nilai: '' });

onMounted(async () => {
  await fetchUsers();
  await fetchOCs();
});

async function fetchUsers() {
  try {
    const res = await axios.get('/api/officer-check/users');
    if (res.data) {
      users.value = res.data;
    }
  } catch (error) {
    console.error('Error fetching users:', error);
    Swal.fire('Error', 'Gagal mengambil data users', 'error');
  }
}

async function fetchOCs() {
  try {
    const res = await axios.get('/api/officer-check');
    if (res.data) {
      ocs.value = res.data;
    }
  } catch (error) {
    console.error('Error fetching OCs:', error);
    Swal.fire('Error', 'Gagal mengambil data Officer Check', 'error');
  }
}

function openAddForm() {
  form.value = { id: null, user_id: null, nilai: '' };
  editMode.value = false;
  showFormInline.value = true;
}
function openEditForm(oc) {
  form.value = { id: oc.id, user_id: users.value.find(u => u.id === oc.user_id), nilai: oc.nilai };
  editMode.value = true;
  showFormInline.value = true;
}
function closeFormInline() {
  showFormInline.value = false;
}

async function saveOC() {
  const payload = { user_id: typeof form.value.user_id === 'object' ? form.value.user_id.id : form.value.user_id, nilai: form.value.nilai };
  const confirm = await Swal.fire({
    title: editMode.value ? 'Simpan perubahan OC?' : 'Tambah Officer Check?',
    text: editMode.value ? 'Data OC akan diperbarui.' : 'Data OC akan ditambahkan.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;
  isSubmitting.value = true;
  try {
    if (editMode.value) {
      await axios.put(`/api/officer-check/${form.value.id}`, payload);
    } else {
      await axios.post('/api/officer-check', payload);
    }
    showFormInline.value = false;
    await fetchOCs();
    Swal.fire('Berhasil', editMode.value ? 'Officer Check berhasil diupdate!' : 'Officer Check berhasil ditambahkan!', 'success');
  } catch (e) {
    Swal.fire('Gagal', 'Terjadi kesalahan saat menyimpan data.', 'error');
  } finally {
    isSubmitting.value = false;
  }
}

async function deleteOC(id) {
  const confirm = await Swal.fire({
    title: 'Hapus Officer Check?',
    text: 'Data OC akan dihapus permanen!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;
  try {
    await axios.delete(`/api/officer-check/${id}`);
    await fetchOCs();
    Swal.fire('Berhasil', 'Officer Check berhasil dihapus!', 'success');
  } catch (e) {
    Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus data.', 'error');
  }
}
</script>

<style scoped>
@import 'vue-multiselect/dist/vue-multiselect.min.css';
</style> 