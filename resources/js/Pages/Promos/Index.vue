<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-gift text-pink-500"></i> Promo
        </h1>
        <Link
          :href="route('promos.create')"
          class="bg-gradient-to-r from-pink-500 to-pink-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
        >
          + Buat Promo
        </Link>
      </div>
      <!-- Search & Filter -->
      <form @submit.prevent="applyFilter" class="flex flex-wrap gap-2 mb-4 items-center">
        <input
          v-model="search"
          type="text"
          placeholder="Cari nama promo..."
          class="border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-pink-200"
          style="min-width:200px"
        />
        <select v-model="type" class="border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-pink-200">
          <option value="">Semua Tipe</option>
          <option value="percent">Diskon Persen</option>
          <option value="nominal">Diskon Nominal</option>
          <option value="bundle">Bundling</option>
          <option value="bogo">Bogo</option>
        </select>
        <button type="submit" class="bg-pink-500 text-white px-4 py-2 rounded font-semibold hover:bg-pink-600 transition">Cari</button>
        <button v-if="search || type" type="button" @click="resetFilter" class="ml-2 bg-gray-200 text-gray-700 px-3 py-2 rounded hover:bg-gray-300 transition">Reset</button>
      </form>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-pink-50 to-pink-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider rounded-tl-2xl">No</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider">Nama Promo</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider">Tipe</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider">Value</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider">Mulai</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider">Akhir</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!promos.length">
              <td colspan="8" class="text-center py-10 text-pink-300">Tidak ada data Promo.</td>
            </tr>
            <tr v-for="(promo, idx) in promos" :key="promo.id" class="hover:bg-pink-50 transition shadow-sm">
              <td class="px-6 py-3">{{ idx + 1 }}</td>
              <td class="px-6 py-3">{{ promo.name }}</td>
              <td class="px-6 py-3">{{ getPromoTypeText(promo.type) }}</td>
              <td class="px-6 py-3">{{ promo.type === 'percent' ? promo.value + '%' : formatCurrency(promo.value) }}</td>
              <td class="px-6 py-3">{{ formatDate(promo.start_date) }}</td>
              <td class="px-6 py-3">{{ formatDate(promo.end_date) }}</td>
              <td class="px-6 py-3">
                <span :class="promo.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'" class="px-2 py-1 rounded-full text-xs font-bold">
                  {{ promo.status === 'active' ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td class="px-6 py-3">
                <Link :href="route('promos.show', promo.id)" class="inline-flex items-center btn btn-xs bg-pink-100 text-pink-800 hover:bg-pink-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-eye mr-1"></i> Detail
                </Link>
                <Link :href="route('promos.edit', promo.id)" class="ml-2 inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-edit mr-1"></i> Edit
                </Link>
                <button @click="handleDelete(promo.id)" :disabled="loadingDeleteId === promo.id" class="ml-2 inline-flex items-center btn btn-xs bg-red-100 text-red-800 hover:bg-red-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50">
                  <i v-if="loadingDeleteId === promo.id" class="fa fa-spinner fa-spin mr-1"></i>
                  <i v-else class="fa fa-trash mr-1"></i> Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { ref } from 'vue';

const props = defineProps({
  promos: Array,
  search: String,
  type: String
});

const search = ref(props.search || '');
const type = ref(props.type || '');

const loadingDeleteId = ref(null);

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

async function handleDelete(id) {
  const confirm = await Swal.fire({
    title: 'Hapus Promo?',
    text: 'Data promo akan dihapus. Lanjutkan?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;
  loadingDeleteId.value = id;
  router.delete(route('promos.destroy', id), {
    onSuccess: () => {
      loadingDeleteId.value = null;
      Swal.fire('Sukses', 'Promo berhasil dihapus!', 'success');
    },
    onError: () => {
      loadingDeleteId.value = null;
      Swal.fire('Error', 'Gagal menghapus promo', 'error');
    }
  });
}

function applyFilter() {
  router.get(route('promos.index'), { search: search.value, type: type.value }, { preserveState: true, replace: true });
}
function resetFilter() {
  search.value = '';
  type.value = '';
  applyFilter();
}

function getPromoTypeText(type) {
  const types = {
    'percent': 'Diskon Persen',
    'nominal': 'Diskon Nominal',
    'bundle': 'Bundling',
    'bogo': 'Buy 1 Get 1'
  };
  return types[type] || type;
}

function formatCurrency(val) {
  if (!val) return '-';
  return Number(val).toLocaleString('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
}
</script> 