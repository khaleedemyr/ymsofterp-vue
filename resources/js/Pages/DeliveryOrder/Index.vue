<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-truck-arrow-right text-blue-500"></i> Delivery Order
        </h1>
        <Link href="/delivery-order/create" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Delivery Order
        </Link>
      </div>
      <div class="flex flex-wrap gap-2 mb-4 items-center">
        <input v-model="search" type="text" placeholder="Cari Packing List / Floor Order / User" class="border rounded px-3 py-2 focus:ring-2 focus:ring-blue-200 min-w-[220px]" />
        <input v-model="dateFrom" type="date" class="border rounded px-3 py-2 focus:ring-2 focus:ring-blue-200" />
        <span class="mx-1">s/d</span>
        <input v-model="dateTo" type="date" class="border rounded px-3 py-2 focus:ring-2 focus:ring-blue-200" />
        <button @click="applyFilter" class="bg-blue-500 text-white px-4 py-2 rounded font-semibold hover:bg-blue-600 transition">Filter</button>
        <button v-if="search || dateFrom || dateTo" @click="resetFilter" type="button" class="ml-2 bg-gray-200 text-gray-700 px-3 py-2 rounded hover:bg-gray-300 transition">Reset</button>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">No DO</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Packing List</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Floor Order</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">User</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!orders.data.length">
              <td colspan="7" class="text-center py-10 text-blue-300">Tidak ada data Delivery Order.</td>
            </tr>
            <tr v-for="(order, idx) in orders.data" :key="order.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3">{{ (orders.current_page - 1) * orders.per_page + idx + 1 }}</td>
              <td class="px-6 py-3">{{ order.number || '-' }}</td>
              <td class="px-6 py-3">{{ formatDate(order.created_at) }}</td>
              <td class="px-6 py-3">{{ order.packing_number || '-' }}</td>
              <td class="px-6 py-3">{{ order.floor_order_number || '-' }}</td>
              <td class="px-6 py-3">{{ order.created_by_name || '-' }}</td>
              <td class="px-6 py-3">
                <Link :href="`/delivery-order/${order.id}`" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-eye mr-1"></i> Detail
                </Link>
                <button @click="handleReprint(order.id)" :disabled="loadingReprintId === order.id" class="ml-2 inline-flex items-center btn btn-xs bg-green-100 text-green-800 hover:bg-green-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50">
                  <i v-if="loadingReprintId === order.id" class="fa fa-spinner fa-spin mr-1"></i>
                  <i v-else class="fa fa-print mr-1"></i> Reprint
                </button>
                <button @click="handleDelete(order.id)" :disabled="loadingDeleteId === order.id" class="ml-2 inline-flex items-center btn btn-xs bg-red-100 text-red-800 hover:bg-red-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50">
                  <i v-if="loadingDeleteId === order.id" class="fa fa-spinner fa-spin mr-1"></i>
                  <i v-else class="fa fa-trash mr-1"></i> Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="orders.total > orders.per_page" class="flex justify-center mt-6">
        <nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
          <button v-for="page in orders.last_page" :key="page" @click="goToPage(page)" :class="['px-3 py-1 border text-sm font-semibold', page === orders.current_page ? 'bg-blue-500 text-white' : 'bg-white text-blue-700 hover:bg-blue-100']">
            {{ page }}
          </button>
        </nav>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { generateStrukPDF } from './generateStrukPDF';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({ orders: Array });
const loadingDeleteId = ref(null);
const loadingReprintId = ref(null);

const page = usePage();
const search = ref(page.props.search || '');
const dateFrom = ref(page.props.dateFrom || '');
const dateTo = ref(page.props.dateTo || '');

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

async function handleDelete(id) {
  const confirm = await Swal.fire({
    title: 'Hapus Delivery Order?',
    text: 'Data dan rollback stok akan dikembalikan. Lanjutkan?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;
  loadingDeleteId.value = id;
  try {
    await router.delete(`/delivery-order/${id}`, {
      onSuccess: async () => {
        await Swal.fire({
          icon: 'success',
          title: 'Sukses',
          text: 'Delivery Order berhasil dihapus dan stok dikembalikan!',
          timer: 1500,
          showConfirmButton: false
        });
      },
      onError: async (err) => {
        await Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: err || 'Gagal menghapus Delivery Order',
        });
      },
      preserveScroll: true,
    });
  } finally {
    loadingDeleteId.value = null;
  }
}

async function handleReprint(orderId) {
  loadingReprintId.value = orderId;
  try {
    const { data } = await axios.get(`/api/delivery-order/${orderId}/struk`);
    await generateStrukPDF({
      ...data,
      showReprintLabel: true
    });
  } catch (e) {
    await Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: 'Gagal mengambil data struk. Coba lagi.'
    });
  } finally {
    loadingReprintId.value = null;
  }
}

function applyFilter() {
  router.get(route('delivery-order.index'), {
    search: search.value,
    dateFrom: dateFrom.value,
    dateTo: dateTo.value
  }, { preserveState: true });
}
function resetFilter() {
  search.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  applyFilter();
}

function goToPage(page) {
  router.get(route('delivery-order.index'), {
    search: search.value,
    dateFrom: dateFrom.value,
    dateTo: dateTo.value,
    page
  }, { preserveState: true });
}
</script> 