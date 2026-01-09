<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-money-bill text-pink-500"></i> Jenis Pembayaran
        </h1>
        <Link
          :href="route('payment-types.create')"
          class="bg-gradient-to-r from-pink-500 to-pink-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
        >
          + Tambah Jenis Pembayaran
        </Link>
      </div>
      <!-- Search & Filter -->
      <form @submit.prevent="applyFilter" class="flex flex-wrap gap-2 mb-4 items-center">
        <input
          v-model="search"
          type="text"
          placeholder="Cari jenis pembayaran..."
          class="border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-pink-200"
          style="min-width:200px"
        />
        <select v-model="status" class="border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-pink-200">
          <option value="">Semua Status</option>
          <option value="active">Aktif</option>
          <option value="inactive">Nonaktif</option>
        </select>
        <button type="submit" class="bg-pink-500 text-white px-4 py-2 rounded font-semibold hover:bg-pink-600 transition">Cari</button>
        <button v-if="search || status" type="button" @click="resetFilter" class="ml-2 bg-gray-200 text-gray-700 px-3 py-2 rounded hover:bg-gray-300 transition">Reset</button>
      </form>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-pink-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700">No</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700">Nama</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700">Kode</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700">Tipe</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-pink-700">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-if="!paymentTypes.length">
              <td colspan="6" class="text-center py-10 text-pink-300">Tidak ada data Jenis Pembayaran.</td>
            </tr>
            <tr v-for="(paymentType, idx) in paymentTypes" :key="paymentType.id" class="hover:bg-pink-50 transition shadow-sm">
              <td class="px-6 py-3">{{ idx + 1 }}</td>
              <td class="px-6 py-3">{{ paymentType.name }}</td>
              <td class="px-6 py-3">{{ paymentType.code }}</td>
              <td class="px-6 py-3">
                <span :class="paymentType.is_bank ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'" class="px-2 py-1 rounded-full text-xs font-bold">
                  {{ paymentType.is_bank ? 'Bank' : 'Non-Bank' }}
                </span>
              </td>
              <td class="px-6 py-3">
                <span :class="paymentType.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'" class="px-2 py-1 rounded-full text-xs font-bold">
                  {{ paymentType.status === 'active' ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td class="px-6 py-3">
                <Link :href="route('payment-types.show', paymentType.id)" class="inline-flex items-center btn btn-xs bg-pink-100 text-pink-800 hover:bg-pink-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-eye mr-1"></i> Detail
                </Link>
                <Link :href="route('payment-types.edit', paymentType.id)" class="ml-2 inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-edit mr-1"></i> Edit
                </Link>
                <Link v-if="paymentType.is_bank" :href="route('payment-types.manage-banks', paymentType.id)" class="ml-2 inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-university mr-1"></i> Manage Bank
                </Link>
                <button @click="handleDelete(paymentType.id)" :disabled="loadingDeleteId === paymentType.id" class="ml-2 inline-flex items-center btn btn-xs bg-red-100 text-red-800 hover:bg-red-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50">
                  <i v-if="loadingDeleteId === paymentType.id" class="fa fa-spinner fa-spin mr-1"></i>
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
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  paymentTypes: Array,
  search: String,
  status: String
});

const search = ref(props.search || '');
const status = ref(props.status || '');
const loadingDeleteId = ref(null);

async function handleDelete(id) {
  const confirm = await Swal.fire({
    title: 'Hapus Jenis Pembayaran?',
    text: 'Data jenis pembayaran akan dihapus. Lanjutkan?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;
  loadingDeleteId.value = id;
  router.delete(route('payment-types.destroy', id), {
    onSuccess: () => {
      loadingDeleteId.value = null;
      Swal.fire('Sukses', 'Jenis pembayaran berhasil dihapus!', 'success');
    },
    onError: () => {
      loadingDeleteId.value = null;
      Swal.fire('Error', 'Gagal menghapus jenis pembayaran', 'error');
    }
  });
}

function applyFilter() {
  router.get(route('payment-types.index'), { search: search.value, status: status.value }, { preserveState: true, replace: true });
}

function resetFilter() {
  search.value = '';
  status.value = '';
  applyFilter();
}
</script> 