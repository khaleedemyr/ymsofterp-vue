<template>
  <AppLayout>
    <div class="max-w-5xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-user-tie text-blue-500"></i> Data Investor Outlet
        </h1>
        <button @click="openAddModal" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <i class="fa-solid fa-plus"></i>
          Tambah Investor
        </button>
      </div>
      <!-- Search and Filter -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input
              type="text"
              v-model="search"
              placeholder="Cari nama, email, atau no HP..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Filter Outlet</label>
            <select
              v-model="outletFilter"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                {{ outlet.name }}
              </option>
            </select>
          </div>
        </div>
      </div>
      <!-- Tabel -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No HP</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet Dimiliki</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="investors.length === 0">
              <td colspan="5" class="text-center py-10 text-gray-400">Tidak ada data investor.</td>
            </tr>
            <tr v-for="inv in filteredInvestors" :key="inv.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">{{ inv.name }}</td>
              <td class="px-6 py-4 whitespace-nowrap">{{ inv.email }}</td>
              <td class="px-6 py-4 whitespace-nowrap">{{ inv.phone }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <ul v-if="inv.outlets && inv.outlets.length" class="list-disc pl-4">
                  <li v-for="o in inv.outlets" :key="o.id">{{ o.name }}</li>
                </ul>
                <span v-else>-</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <button class="px-2 py-1 bg-yellow-400 rounded mr-2" @click="openEditModal(inv)">Edit</button>
                <button class="px-2 py-1 bg-red-500 text-white rounded" @click="deleteInvestor(inv.id)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="page in totalPages"
          :key="page"
          @click="goToPage(page)"
          :class="[
            'px-3 py-1 rounded-lg border text-sm font-semibold',
            page === currentPage ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
            'cursor-pointer'
          ]"
        >
          {{ page }}
        </button>
      </div>
      <!-- Modal Add/Edit -->
      <div v-if="showFormInline" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-40 transition-all">
        <div class="max-w-lg mx-auto py-8 px-2">
          <h2 class="text-2xl font-bold text-blue-700 mb-6">{{ editMode ? 'Edit Investor' : 'Tambah Investor' }}</h2>
          <form @submit.prevent="saveInvestor" class="space-y-5 bg-white rounded-2xl shadow-xl p-8">
            <div>
              <label class="block text-sm font-medium text-gray-700">Nama</label>
              <input v-model="form.name" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="100" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Email</label>
              <input v-model="form.email" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">No HP</label>
              <input v-model="form.phone" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Outlet Dimiliki</label>
              <Multiselect v-model="form.outlet_ids" :options="outlets" :multiple="true" label="name" track-by="id" placeholder="Pilih Outlet" class="mt-1" />
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
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import Swal from 'sweetalert2';

const investors = ref([]);
const outlets = ref([]);
const showFormInline = ref(false);
const editMode = ref(false);
const form = ref({ id: null, name: '', email: '', phone: '', outlet_ids: [] });
const search = ref('');
const outletFilter = ref('');
const currentPage = ref(1);
const pageSize = 10;
const dataReady = ref(false);
const isSubmitting = ref(false);

onMounted(async () => {
  try {
    const resOutlets = await axios.get('/api/outlets/investor');
    
    // Handle different response formats
    if (resOutlets.data.outlets) {
      outlets.value = resOutlets.data.outlets;
      if (resOutlets.data.warning) {
        Swal.fire('Warning', resOutlets.data.warning, 'warning');
      }
    } else if (Array.isArray(resOutlets.data)) {
      outlets.value = resOutlets.data;
    } else {
      outlets.value = [];
    }
    
    await fetchInvestors();
    dataReady.value = true;
  } catch (error) {
    console.error('Error loading data:', error);
    if (error.response && error.response.data && error.response.data.error) {
      Swal.fire('Error', error.response.data.error, 'error');
    } else {
      Swal.fire('Error', 'Gagal memuat data outlet. Silakan refresh halaman.', 'error');
    }
  }
});

async function fetchInvestors() {
  try {
    const res = await axios.get('/api/investors');
    investors.value = res.data;
  } catch (error) {
    console.error('Error fetching investors:', error);
    Swal.fire('Error', 'Gagal memuat data investor. Silakan refresh halaman.', 'error');
  }
}

const filteredInvestors = computed(() => {
  let data = investors.value;
  if (search.value) {
    const s = search.value.toLowerCase();
    data = data.filter(inv =>
      inv.name.toLowerCase().includes(s) ||
      (inv.email && inv.email.toLowerCase().includes(s)) ||
      (inv.phone && inv.phone.toLowerCase().includes(s))
    );
  }
  if (outletFilter.value) {
    data = data.filter(inv => inv.outlets && inv.outlets.some(o => o.id === Number(outletFilter.value)));
  }
  // Pagination
  return data.slice((currentPage.value - 1) * pageSize, currentPage.value * pageSize);
});

const totalPages = computed(() => {
  let data = investors.value;
  if (search.value) {
    const s = search.value.toLowerCase();
    data = data.filter(inv =>
      inv.name.toLowerCase().includes(s) ||
      (inv.email && inv.email.toLowerCase().includes(s)) ||
      (inv.phone && inv.phone.toLowerCase().includes(s))
    );
  }
  if (outletFilter.value) {
    data = data.filter(inv => inv.outlets && inv.outlets.some(o => o.id === Number(outletFilter.value)));
  }
  return Math.ceil(data.length / pageSize) || 1;
});

function goToPage(page) {
  currentPage.value = page;
}

function openAddModal() {
  form.value = { id: null, name: '', email: '', phone: '', outlet_ids: [] };
  editMode.value = false;
  showFormInline.value = true;
}

function openEditModal(inv) {
  if (!dataReady.value) return;
  form.value = {
    id: inv.id,
    name: inv.name,
    email: inv.email,
    phone: inv.phone,
    outlet_ids: inv.outlets ? inv.outlets.map(o => o.id) : []
  };
  editMode.value = true;
  showFormInline.value = true;
}

function closeFormInline() {
  showFormInline.value = false;
}

async function saveInvestor() {
  const outlet_ids = (form.value.outlet_ids || []).map(o => typeof o === 'object' ? o.id : o);
  const payload = { ...form.value, outlet_ids };
  // Konfirmasi sebelum simpan
  const confirm = await Swal.fire({
    title: editMode.value ? 'Simpan perubahan investor?' : 'Tambah investor baru?',
    text: editMode.value ? 'Data investor akan diperbarui.' : 'Data investor akan ditambahkan.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;
  isSubmitting.value = true;
  try {
    if (editMode.value) {
      await axios.put(`/api/investors/${form.value.id}`, payload);
    } else {
      await axios.post('/api/investors', payload);
    }
    showFormInline.value = false;
    await fetchInvestors();
    Swal.fire('Berhasil', editMode.value ? 'Investor berhasil diupdate!' : 'Investor berhasil ditambahkan!', 'success');
  } catch (e) {
    Swal.fire('Gagal', 'Terjadi kesalahan saat menyimpan data.', 'error');
  } finally {
    isSubmitting.value = false;
  }
}

async function deleteInvestor(id) {
  const confirm = await Swal.fire({
    title: 'Hapus investor?',
    text: 'Data investor akan dihapus permanen!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;
  try {
    await axios.delete(`/api/investors/${id}`);
    await fetchInvestors();
    Swal.fire('Berhasil', 'Investor berhasil dihapus!', 'success');
  } catch (e) {
    Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus data.', 'error');
  }
}
</script>

<style scoped>
@import 'vue-multiselect/dist/vue-multiselect.min.css';
.swal2-container {
  z-index: 99999999999 !important;
}
</style> 