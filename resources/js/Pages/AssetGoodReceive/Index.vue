<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-boxes-stacked text-blue-500"></i> Asset Good Receive
        </h1>
        <a
          href="/asset-good-receives/create"
          class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-5 py-2.5 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
        >
          <i class="fa-solid fa-plus mr-2"></i> Create New
        </a>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-3 mb-5 items-center">
        <input
          v-model="filters.search"
          @input="onFilterChange"
          type="text"
          placeholder="Search GR Number, PO Number..."
          class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <input
          v-model="filters.from"
          @change="onFilterChange"
          type="date"
          class="px-3 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <span class="text-gray-500">-</span>
        <input
          v-model="filters.to"
          @change="onFilterChange"
          type="date"
          class="px-3 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <select
          v-model="filters.status"
          @change="onFilterChange"
          class="px-3 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="">All Status</option>
          <option value="draft">Draft</option>
          <option value="completed">Completed</option>
        </select>
        <select
          v-if="user.id_outlet == 1"
          v-model="filters.outlet_id"
          @change="onFilterChange"
          class="px-3 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="">All Outlets</option>
          <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
            {{ outlet.nama_outlet }}
          </option>
        </select>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">GR Number</th>
              <th class="px-5 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">PO Number</th>
              <th class="px-5 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet</th>
              <th class="px-5 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-5 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Receive Date</th>
              <th class="px-5 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-5 py-3 text-right text-xs font-bold text-blue-700 uppercase tracking-wider">Total</th>
              <th class="px-5 py-3 text-center text-xs font-bold text-blue-700 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!purchaseOrders.data || !purchaseOrders.data.length">
              <td colspan="8" class="text-center py-12 text-gray-400">
                <i class="fa-solid fa-inbox text-4xl mb-3 block"></i>
                No Asset Good Receive data found.
              </td>
            </tr>
            <tr v-for="gr in purchaseOrders.data" :key="gr.id" class="hover:bg-blue-50 transition">
              <td class="px-5 py-3 font-semibold text-gray-800">{{ gr.gr_number }}</td>
              <td class="px-5 py-3 text-gray-700">{{ gr.po_number }}</td>
              <td class="px-5 py-3 text-gray-700">{{ gr.outlet_name }}</td>
              <td class="px-5 py-3 text-gray-700">{{ gr.warehouse_name || '-' }}</td>
              <td class="px-5 py-3 text-gray-700">{{ gr.receive_date }}</td>
              <td class="px-5 py-3">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="gr.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                >
                  {{ gr.status }}
                </span>
              </td>
              <td class="px-5 py-3 text-right font-medium text-gray-800">{{ formatCurrency(gr.total) }}</td>
              <td class="px-5 py-3 text-center">
                <div class="flex justify-center gap-2">
                  <a
                    :href="`/asset-good-receives/${gr.id}`"
                    class="inline-flex items-center px-2.5 py-1 bg-blue-100 text-blue-800 hover:bg-blue-200 rounded text-xs font-semibold transition"
                  >
                    <i class="fa-solid fa-eye mr-1"></i> View
                  </a>
                  <a
                    v-if="gr.status === 'draft'"
                    :href="`/asset-good-receives/${gr.id}/edit`"
                    class="inline-flex items-center px-2.5 py-1 bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded text-xs font-semibold transition"
                  >
                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                  </a>
                  <button
                    v-if="gr.status === 'draft'"
                    @click="handleDelete(gr.id, gr.gr_number)"
                    class="inline-flex items-center px-2.5 py-1 bg-red-100 text-red-700 hover:bg-red-200 rounded text-xs font-semibold transition"
                  >
                    <i class="fa-solid fa-trash mr-1"></i> Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in purchaseOrders.links"
          :key="link.label"
          :disabled="!link.url"
          @click="goToPage(link.url)"
          v-html="link.label"
          class="px-3 py-1 rounded-lg border text-sm font-semibold"
          :class="[
            link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
            !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
          ]"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  purchaseOrders: Object,
  filters: Object,
  user: Object,
  outlets: Array,
});

const filters = reactive({
  search: props.filters?.search || '',
  from: props.filters?.from || '',
  to: props.filters?.to || '',
  status: props.filters?.status || '',
  outlet_id: props.filters?.outlet_id || '',
});

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount || 0);
};

const applyFilters = debounce(() => {
  router.get('/asset-good-receives', {
    search: filters.search,
    from: filters.from,
    to: filters.to,
    status: filters.status,
    outlet_id: filters.outlet_id,
  }, { preserveState: true, replace: true });
}, 400);

function onFilterChange() {
  applyFilters();
}

function goToPage(url) {
  if (url) {
    router.get(url, {
      search: filters.search,
      from: filters.from,
      to: filters.to,
      status: filters.status,
      outlet_id: filters.outlet_id,
    }, { preserveState: true, replace: true });
  }
}

async function handleDelete(id, grNumber) {
  const result = await Swal.fire({
    title: 'Delete Good Receive?',
    text: `Are you sure you want to delete ${grNumber}? This action cannot be undone.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, Delete',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.delete(`/asset-good-receives/${id}`);
      if (response.data.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Deleted!',
          text: 'Good Receive has been deleted successfully.',
          timer: 2000,
          showConfirmButton: false,
        });
        router.get('/asset-good-receives', {
          search: filters.search,
          from: filters.from,
          to: filters.to,
          status: filters.status,
          outlet_id: filters.outlet_id,
        }, { preserveState: true, replace: true });
      }
    } catch (error) {
      await Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: error.response?.data?.message || 'Failed to delete Good Receive.',
        confirmButtonColor: '#3085d6',
      });
    }
  }
}
</script>
